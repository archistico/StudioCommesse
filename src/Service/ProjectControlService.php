<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Enum\EconomicClosureStatus;
use App\Enum\OperationalClosureStatus;
use App\Enum\OverallClosureStatus;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Query\ClientControlRow;
use App\Query\CollaboratorControlRow;
use App\Query\ControlDashboard;
use App\Query\ControlSearchCriteria;
use App\Query\PeriodControlRow;
use App\Query\ProjectControlRow;
use App\Repository\ControlRepository;
use DateTimeImmutable;

final readonly class ProjectControlService
{
    public const STALLED_AFTER_DAYS = 14;
    public const OVERLOAD_ACTIVITY_COUNT = 8;
    public const OVERLOAD_REMAINING_MINUTES = 2_400;

    public function __construct(private ControlRepository $repository)
    {
    }

    public function build(ControlSearchCriteria $criteria, ?DateTimeImmutable $now = null): ControlDashboard
    {
        $now ??= new DateTimeImmutable();
        $projects = array_map(
            fn (array $row): ProjectControlRow => $this->mapProjectRow($row, $now),
            $this->repository->findProjectMetrics($criteria->clientId, $criteria->responsibleId),
        );

        $projects = array_values(array_filter(
            $projects,
            static function (ProjectControlRow $row) use ($criteria): bool {
                if (null !== $criteria->overallStatus && $row->overallStatus !== $criteria->overallStatus) {
                    return false;
                }

                return !$criteria->onlyCritical || $row->isCritical();
            },
        ));
        $this->sortProjects($projects, $criteria);

        $collaborators = array_map(
            function (array $row): CollaboratorControlRow {
                $openActivities = (int) ($row['open_activities'] ?? 0);
                $remainingMinutes = (int) ($row['remaining_minutes'] ?? 0);
                $role = UserRole::tryFrom((string) ($row['role'] ?? ''));

                return new CollaboratorControlRow(
                    userId: (int) ($row['user_id'] ?? 0),
                    displayName: (string) ($row['display_name'] ?? ''),
                    roleLabel: $role?->label() ?? 'Utente',
                    active: (bool) ($row['active'] ?? false),
                    openActivities: $openActivities,
                    overdueActivities: (int) ($row['overdue_activities'] ?? 0),
                    remainingMinutes: $remainingMinutes,
                    workedMinutes: (int) ($row['worked_minutes'] ?? 0),
                    billableMinutes: (int) ($row['billable_minutes'] ?? 0),
                    projectCount: (int) ($row['project_count'] ?? 0),
                    overloaded: $openActivities > self::OVERLOAD_ACTIVITY_COUNT
                        || $remainingMinutes > self::OVERLOAD_REMAINING_MINUTES,
                );
            },
            $this->repository->findCollaboratorMetrics(
                $criteria->periodFrom,
                $criteria->periodBefore,
                $criteria->clientId,
                $criteria->responsibleId,
                $now,
            ),
        );
        usort($collaborators, static function (CollaboratorControlRow $left, CollaboratorControlRow $right): int {
            if ($left->overloaded !== $right->overloaded) {
                return $left->overloaded ? -1 : 1;
            }

            $comparison = $right->openActivities <=> $left->openActivities;
            if (0 !== $comparison) {
                return $comparison;
            }

            $comparison = $right->remainingMinutes <=> $left->remainingMinutes;

            return 0 !== $comparison ? $comparison : ($left->displayName <=> $right->displayName);
        });

        $clients = $this->buildClientRows($projects, $criteria);
        $periods = $this->buildPeriodRows($criteria);

        return new ControlDashboard(
            projects: $projects,
            collaborators: $collaborators,
            clients: $clients,
            periods: $periods,
            overallClosedCount: count(array_filter($projects, static fn (ProjectControlRow $row): bool => OverallClosureStatus::Closed === $row->overallStatus)),
            toCollectCount: count(array_filter($projects, static fn (ProjectControlRow $row): bool => OverallClosureStatus::ToCollect === $row->overallStatus)),
            criticalProjectCount: count(array_filter($projects, static fn (ProjectControlRow $row): bool => $row->isCritical())),
            overloadedCollaboratorCount: count(array_filter($collaborators, static fn (CollaboratorControlRow $row): bool => $row->overloaded)),
        );
    }

    public function analyze(Project $project, ?DateTimeImmutable $now = null): ?ProjectControlRow
    {
        $id = $project->getId();
        if (null === $id) {
            return null;
        }

        $row = $this->repository->findProjectMetrics(projectId: $id)[0] ?? null;
        if (!is_array($row)) {
            return null;
        }

        return $this->mapProjectRow($row, $now ?? new DateTimeImmutable());
    }

    /** @param array<string, mixed> $row */
    private function mapProjectRow(array $row, DateTimeImmutable $now): ProjectControlRow
    {
        $projectStatus = ProjectStatus::tryFrom((string) ($row['status'] ?? '')) ?? ProjectStatus::NotStarted;
        $openActivityCount = (int) ($row['open_activity_count'] ?? 0);
        $activityCount = (int) ($row['activity_count'] ?? 0);
        $runningTimerCount = (int) ($row['running_timer_count'] ?? 0);
        $estimatedAmountCents = (int) ($row['estimated_amount_cents'] ?? 0);
        $paymentCents = (int) ($row['payment_cents'] ?? 0);
        $lastOperationalAt = new DateTimeImmutable((string) ($row['last_operational_at'] ?? '1970-01-01 00:00:00'));
        $dueDateValue = $row['due_date'] ?? null;
        $dueDate = is_string($dueDateValue) && '' !== $dueDateValue ? new DateTimeImmutable($dueDateValue) : null;
        $today = $now->setTime(0, 0);
        $overdue = null !== $dueDate && !$projectStatus->isClosed() && $dueDate < $today;
        $stalled = !$projectStatus->isClosed()
            && $lastOperationalAt < $now->modify('-'.self::STALLED_AFTER_DAYS.' days');

        $operational = $this->operationalStatus($projectStatus, $openActivityCount, $runningTimerCount, $activityCount);
        $economic = $this->economicStatus($projectStatus, $estimatedAmountCents, $paymentCents);
        $overall = $this->overallStatus($operational, $economic);

        $estimatedMinutes = (int) ($row['estimated_minutes'] ?? 0);
        $actualMinutes = (int) ($row['actual_minutes'] ?? 0);
        $labourCostCents = (int) ($row['labour_cost_cents'] ?? 0);
        $expenseCostCents = (int) ($row['expense_cents'] ?? 0);
        $alerts = [];

        if (OperationalClosureStatus::Inconsistent === $operational) {
            $alerts[] = 'Stato chiuso con attività o timer ancora aperti';
        }
        if ($overdue) {
            $alerts[] = 'Scadenza superata';
        }
        if ($stalled) {
            $alerts[] = sprintf('Nessun avanzamento da oltre %d giorni', self::STALLED_AFTER_DAYS);
        }
        if ($estimatedMinutes > 0 && $actualMinutes > $estimatedMinutes) {
            $alerts[] = 'Ore consuntivate oltre la stima';
        }
        if ($estimatedAmountCents > 0 && $labourCostCents + $expenseCostCents > $estimatedAmountCents) {
            $alerts[] = 'Costo totale oltre il preventivo';
        }
        if (EconomicClosureStatus::Unconfigured === $economic) {
            $alerts[] = 'Preventivo non configurato';
        }
        if ($runningTimerCount > 0 && $projectStatus->isClosed()) {
            $alerts[] = 'Timer attivo su commessa chiusa';
        }

        return new ProjectControlRow(
            projectId: (int) ($row['project_id'] ?? 0),
            code: (string) ($row['code'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            clientId: (int) ($row['client_id'] ?? 0),
            clientName: (string) ($row['client_name'] ?? ''),
            responsibleId: (int) ($row['responsible_id'] ?? 0),
            responsibleName: (string) ($row['responsible_name'] ?? ''),
            projectStatus: $projectStatus,
            dueDate: $dueDate,
            activityCount: $activityCount,
            openActivityCount: $openActivityCount,
            runningTimerCount: $runningTimerCount,
            estimatedMinutes: $estimatedMinutes,
            remainingMinutes: (int) ($row['remaining_minutes'] ?? 0),
            actualMinutes: $actualMinutes,
            estimatedAmountCents: $estimatedAmountCents,
            labourCostCents: $labourCostCents,
            expenseCostCents: $expenseCostCents,
            paymentsCents: $paymentCents,
            lastOperationalAt: $lastOperationalAt,
            overdue: $overdue,
            stalled: $stalled,
            operationalStatus: $operational,
            economicStatus: $economic,
            overallStatus: $overall,
            alerts: $alerts,
        );
    }

    private function operationalStatus(
        ProjectStatus $projectStatus,
        int $openActivityCount,
        int $runningTimerCount,
        int $activityCount,
    ): OperationalClosureStatus {
        if ($projectStatus->isClosed()) {
            return 0 === $openActivityCount && 0 === $runningTimerCount
                ? OperationalClosureStatus::Closed
                : OperationalClosureStatus::Inconsistent;
        }

        if ($activityCount > 0 && 0 === $openActivityCount && 0 === $runningTimerCount) {
            return OperationalClosureStatus::Ready;
        }

        return OperationalClosureStatus::Open;
    }

    private function economicStatus(ProjectStatus $projectStatus, int $estimatedAmountCents, int $paymentsCents): EconomicClosureStatus
    {
        if (ProjectStatus::Cancelled === $projectStatus) {
            return EconomicClosureStatus::NotApplicable;
        }
        if ($estimatedAmountCents <= 0) {
            return EconomicClosureStatus::Unconfigured;
        }
        if ($paymentsCents >= $estimatedAmountCents) {
            return EconomicClosureStatus::Closed;
        }
        if ($paymentsCents > 0) {
            return EconomicClosureStatus::Partial;
        }

        return EconomicClosureStatus::Open;
    }

    private function overallStatus(
        OperationalClosureStatus $operational,
        EconomicClosureStatus $economic,
    ): OverallClosureStatus {
        if (OperationalClosureStatus::Inconsistent === $operational || EconomicClosureStatus::Unconfigured === $economic) {
            return OverallClosureStatus::Attention;
        }
        if ($operational->isClosed() && $economic->isClosed()) {
            return OverallClosureStatus::Closed;
        }
        if ($operational->isClosed()) {
            return OverallClosureStatus::ToCollect;
        }
        if ($economic->isClosed()) {
            return OverallClosureStatus::WorkOpen;
        }

        return OverallClosureStatus::Open;
    }

    /**
     * @param list<ProjectControlRow> $projects
     * @return list<ClientControlRow>
     */
    private function buildClientRows(array $projects, ControlSearchCriteria $criteria): array
    {
        $periodMetrics = [];
        foreach ($this->repository->findClientPeriodMetrics(
            $criteria->periodFrom,
            $criteria->periodBefore,
            $criteria->clientId,
            $criteria->responsibleId,
        ) as $row) {
            $periodMetrics[(int) ($row['client_id'] ?? 0)] = $row;
        }

        /** @var array<int, array{name: string, project_count: int, open_count: int, closed_count: int, critical_count: int}> $grouped */
        $grouped = [];
        foreach ($projects as $project) {
            $grouped[$project->clientId] ??= [
                'name' => $project->clientName,
                'project_count' => 0,
                'open_count' => 0,
                'closed_count' => 0,
                'critical_count' => 0,
            ];
            ++$grouped[$project->clientId]['project_count'];
            if (OverallClosureStatus::Closed === $project->overallStatus) {
                ++$grouped[$project->clientId]['closed_count'];
            } else {
                ++$grouped[$project->clientId]['open_count'];
            }
            if ($project->isCritical()) {
                ++$grouped[$project->clientId]['critical_count'];
            }
        }

        $clients = [];
        foreach ($grouped as $clientId => $current) {
            $period = $periodMetrics[$clientId] ?? [];
            $clients[] = new ClientControlRow(
                clientId: $clientId,
                clientName: (string) $current['name'],
                projectCount: (int) $current['project_count'],
                openProjectCount: (int) $current['open_count'],
                closedProjectCount: (int) $current['closed_count'],
                criticalProjectCount: (int) $current['critical_count'],
                workedMinutes: (int) ($period['worked_minutes'] ?? 0),
                billableMinutes: (int) ($period['billable_minutes'] ?? 0),
                labourCostCents: (int) ($period['labour_cost_cents'] ?? 0),
                expenseCents: (int) ($period['expense_cents'] ?? 0),
                paymentCents: (int) ($period['payment_cents'] ?? 0),
            );
        }

        usort($clients, static fn (ClientControlRow $left, ClientControlRow $right): int =>
            [$right->criticalProjectCount, $right->openProjectCount, $left->clientName]
            <=> [$left->criticalProjectCount, $left->openProjectCount, $right->clientName]
        );

        return $clients;
    }

    /** @return list<PeriodControlRow> */
    private function buildPeriodRows(ControlSearchCriteria $criteria): array
    {
        $metrics = [];
        foreach ($this->repository->findPeriodMetrics(
            $criteria->periodFrom,
            $criteria->periodBefore,
            $criteria->clientId,
            $criteria->responsibleId,
        ) as $row) {
            $metrics[(string) ($row['month_key'] ?? '')] = $row;
        }

        $periods = [];
        $month = $criteria->periodFrom->modify('first day of this month midnight');
        $lastMonth = $criteria->periodBefore->modify('-1 day')->modify('first day of this month midnight');
        while ($month <= $lastMonth) {
            $key = $month->format('Y-m');
            $row = $metrics[$key] ?? [];
            $periods[] = new PeriodControlRow(
                month: $month,
                workedMinutes: (int) ($row['worked_minutes'] ?? 0),
                billableMinutes: (int) ($row['billable_minutes'] ?? 0),
                labourCostCents: (int) ($row['labour_cost_cents'] ?? 0),
                expenseCents: (int) ($row['expense_cents'] ?? 0),
                paymentCents: (int) ($row['payment_cents'] ?? 0),
            );
            $month = $month->modify('+1 month');
        }

        return array_reverse($periods);
    }

    /** @param list<ProjectControlRow> $projects */
    private function sortProjects(array &$projects, ControlSearchCriteria $criteria): void
    {
        usort($projects, function (ProjectControlRow $left, ProjectControlRow $right) use ($criteria): int {
            $comparison = match ($criteria->sort) {
                ControlSearchCriteria::SORT_DUE_DATE => ($left->dueDate?->getTimestamp() ?? PHP_INT_MAX) <=> ($right->dueDate?->getTimestamp() ?? PHP_INT_MAX),
                ControlSearchCriteria::SORT_CODE => $left->code <=> $right->code,
                ControlSearchCriteria::SORT_ACTUAL_HOURS => $left->actualMinutes <=> $right->actualMinutes,
                ControlSearchCriteria::SORT_TIME_DEVIATION => $left->getTimeDeviationMinutes() <=> $right->getTimeDeviationMinutes(),
                ControlSearchCriteria::SORT_MARGIN => $left->getMarginCents() <=> $right->getMarginCents(),
                default => $left->getCriticalityScore() <=> $right->getCriticalityScore(),
            };

            if (0 === $comparison) {
                $comparison = $left->code <=> $right->code;
            }

            return $criteria->isDescending() ? -$comparison : $comparison;
        });
    }
}
