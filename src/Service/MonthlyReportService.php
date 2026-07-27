<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\AuditAction;
use App\Enum\ProjectPriority;
use App\Enum\ProjectStatus;
use App\Query\MonthlyActionEventRow;
use App\Query\MonthlyActionReportRow;
use App\Query\MonthlyProjectReportRow;
use App\Query\MonthlyReport;
use App\Query\MonthlyTimeEntryReportRow;
use App\Repository\MonthlyReportRepository;
use DateTimeImmutable;

final readonly class MonthlyReportService
{
    public function __construct(private MonthlyReportRepository $repository)
    {
    }

    public function build(DateTimeImmutable $month, ?int $projectId = null): MonthlyReport
    {
        $periodFrom = $month->modify('first day of this month midnight');
        $periodBefore = $periodFrom->modify('+1 month');

        $projects = array_map(
            fn (array $row): MonthlyProjectReportRow => $this->mapProject($row),
            $this->repository->findProjectMetrics($periodFrom, $periodBefore, $projectId),
        );
        $timeEntries = array_map(
            fn (array $row): MonthlyTimeEntryReportRow => $this->mapTimeEntry($row),
            $this->repository->findTimeEntries($periodFrom, $periodBefore, $projectId),
        );

        $actions = [];
        foreach ($this->repository->findActionCounts($periodFrom, $periodBefore, $projectId) as $row) {
            $action = AuditAction::tryFrom((string) ($row['action'] ?? ''));
            if ($action instanceof AuditAction) {
                $actions[] = new MonthlyActionReportRow($action, (int) ($row['action_count'] ?? 0));
            }
        }

        $events = [];
        foreach ($this->repository->findActionEvents($periodFrom, $periodBefore, $projectId) as $row) {
            $action = AuditAction::tryFrom((string) ($row['action'] ?? ''));
            if (!$action instanceof AuditAction) {
                continue;
            }
            $details = $this->details($row['details'] ?? null);
            $events[] = new MonthlyActionEventRow(
                id: (int) ($row['id'] ?? 0),
                occurredAt: new DateTimeImmutable((string) ($row['occurred_at'] ?? 'now')),
                action: $action,
                actor: (string) ($row['actor_identifier'] ?? 'Sistema'),
                summary: $this->describe($action, $details),
                subjectType: is_string($row['subject_type'] ?? null) ? $row['subject_type'] : null,
                subjectId: null === ($row['subject_id'] ?? null) ? null : (int) $row['subject_id'],
            );
        }

        $workedMinutes = (int) array_sum(array_map(static fn (MonthlyProjectReportRow $row): int => $row->workedMinutes, $projects));
        $billableMinutes = (int) array_sum(array_map(static fn (MonthlyProjectReportRow $row): int => $row->billableMinutes, $projects));
        $expenseCents = (int) array_sum(array_map(static fn (MonthlyProjectReportRow $row): int => $row->expenseCents, $projects));
        $paymentCents = (int) array_sum(array_map(static fn (MonthlyProjectReportRow $row): int => $row->paymentCents, $projects));
        $attachmentCount = (int) array_sum(array_map(static fn (MonthlyProjectReportRow $row): int => $row->attachmentCount, $projects));
        $movedProjectCount = count(array_filter($projects, static fn (MonthlyProjectReportRow $row): bool => $row->hasMovement()));
        $contributors = [];
        foreach ($timeEntries as $entry) {
            $contributors[$entry->userId] = true;
        }

        return new MonthlyReport(
            month: $periodFrom,
            periodFrom: $periodFrom,
            periodBefore: $periodBefore,
            projects: $projects,
            timeEntries: $timeEntries,
            actions: $actions,
            events: $events,
            workedMinutes: $workedMinutes,
            billableMinutes: $billableMinutes,
            timeEntryCount: count($timeEntries),
            contributorCount: count($contributors),
            movedProjectCount: $movedProjectCount,
            inactiveProjectCount: count($projects) - $movedProjectCount,
            expenseCents: $expenseCents,
            paymentCents: $paymentCents,
            attachmentCount: $attachmentCount,
            actionCount: (int) array_sum(array_map(static fn (MonthlyActionReportRow $row): int => $row->count, $actions)),
        );
    }

    /** @param array<string, mixed> $row */
    private function mapProject(array $row): MonthlyProjectReportRow
    {
        $lastMovement = (string) ($row['last_movement_at'] ?? '');

        return new MonthlyProjectReportRow(
            projectId: (int) ($row['project_id'] ?? 0),
            code: (string) ($row['code'] ?? ''),
            name: (string) ($row['name'] ?? ''),
            clientName: (string) ($row['client_name'] ?? ''),
            responsibleName: (string) ($row['responsible_name'] ?? ''),
            status: ProjectStatus::tryFrom((string) ($row['status'] ?? '')) ?? ProjectStatus::NotStarted,
            priority: ProjectPriority::tryFrom((string) ($row['priority'] ?? '')) ?? ProjectPriority::Normal,
            dueDate: $this->dateOrNull($row['due_date'] ?? null),
            activityCount: (int) ($row['activity_count'] ?? 0),
            openActivityCount: (int) ($row['open_activity_count'] ?? 0),
            completedActivityCount: (int) ($row['completed_activity_count'] ?? 0),
            overdueActivityCount: (int) ($row['overdue_activity_count'] ?? 0),
            averageProgressPercent: (int) ($row['average_progress_percent'] ?? 0),
            remainingMinutes: (int) ($row['remaining_minutes'] ?? 0),
            timeEntryCount: (int) ($row['time_entry_count'] ?? 0),
            workedMinutes: (int) ($row['worked_minutes'] ?? 0),
            billableMinutes: (int) ($row['billable_minutes'] ?? 0),
            contributorCount: (int) ($row['contributor_count'] ?? 0),
            labourCostCents: (int) ($row['labour_cost_cents'] ?? 0),
            expenseCount: (int) ($row['expense_count'] ?? 0),
            expenseCents: (int) ($row['expense_cents'] ?? 0),
            paymentCount: (int) ($row['payment_count'] ?? 0),
            paymentCents: (int) ($row['payment_cents'] ?? 0),
            attachmentCount: (int) ($row['attachment_count'] ?? 0),
            lastMovementAt: '' === $lastMovement || str_starts_with($lastMovement, '1970-01-01') ? null : new DateTimeImmutable($lastMovement),
        );
    }

    /** @param array<string, mixed> $row */
    private function mapTimeEntry(array $row): MonthlyTimeEntryReportRow
    {
        return new MonthlyTimeEntryReportRow(
            entryId: (int) ($row['entry_id'] ?? 0),
            startedAt: new DateTimeImmutable((string) ($row['started_at'] ?? 'now')),
            endedAt: $this->dateOrNull($row['ended_at'] ?? null),
            durationMinutes: (int) ($row['duration_minutes'] ?? 0),
            billable: (bool) ($row['billable'] ?? false),
            costCents: (int) ($row['cost_snapshot_cents'] ?? 0),
            description: (string) ($row['description'] ?? ''),
            userId: (int) ($row['user_id'] ?? 0),
            userName: (string) ($row['user_name'] ?? ''),
            activityId: (int) ($row['activity_id'] ?? 0),
            activityTitle: (string) ($row['activity_title'] ?? ''),
            projectId: (int) ($row['project_id'] ?? 0),
            projectCode: (string) ($row['project_code'] ?? ''),
            projectName: (string) ($row['project_name'] ?? ''),
        );
    }

    private function dateOrNull(mixed $value): ?DateTimeImmutable
    {
        return is_string($value) && '' !== $value ? new DateTimeImmutable($value) : null;
    }

    /** @return array<string, bool|float|int|string|null> */
    private function details(mixed $value): array
    {
        if (is_array($value)) {
            $decoded = $value;
        } elseif (is_string($value) && '' !== $value) {
            $decoded = json_decode($value, true);
        } else {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }

        /** @var array<string, bool|float|int|string|null> $details */
        $details = [];
        foreach ($decoded as $key => $item) {
            if (is_string($key) && (is_scalar($item) || null === $item)) {
                $details[$key] = $item;
            }
        }

        return $details;
    }

    /** @param array<string, bool|float|int|string|null> $details */
    private function describe(AuditAction $action, array $details): string
    {
        $project = $this->text($details['project'] ?? null) ?? $this->text($details['code'] ?? null);
        $title = $this->text($details['title'] ?? null) ?? $this->text($details['activity'] ?? null);
        $name = $this->text($details['name'] ?? null) ?? $this->text($details['original_name'] ?? null);
        $amount = isset($details['amount_cents']) ? ' · '.number_format((int) $details['amount_cents'] / 100, 2, ',', '.').' €' : '';
        $minutes = isset($details['minutes']) ? ' · '.(int) $details['minutes'].' min' : '';

        return match ($action) {
            AuditAction::ProjectCreated, AuditAction::ProjectUpdated, AuditAction::ProjectArchived, AuditAction::ProjectRestored => $project ?? $name ?? 'Commessa',
            AuditAction::ActivityCreated, AuditAction::ActivityUpdated => trim(($project ? $project.' · ' : '').($title ?? 'Attività')),
            AuditAction::TimeEntryCreated, AuditAction::TimerStarted => $title ?? 'Registrazione ore',
            AuditAction::TimerStopped => 'Timer'.$minutes,
            AuditAction::ExpenseCreated, AuditAction::ExpenseUpdated, AuditAction::ExpenseDeleted => ($project ?? 'Commessa').$amount,
            AuditAction::PaymentCreated, AuditAction::PaymentUpdated, AuditAction::PaymentDeleted => ($project ?? 'Commessa').$amount,
            AuditAction::AttachmentUploaded, AuditAction::AttachmentUpdated, AuditAction::AttachmentDownloaded, AuditAction::AttachmentDeleted => $name ?? 'Documento',
            AuditAction::ClientCreated, AuditAction::ClientUpdated, AuditAction::ClientArchived, AuditAction::ClientRestored => $name ?? 'Cliente',
            AuditAction::UserCreated, AuditAction::UserUpdated => $this->text($details['username'] ?? null) ?? 'Utente',
            AuditAction::LoginSucceeded, AuditAction::LoginFailed => 'Accesso applicativo',
            AuditAction::FixturesLoaded => 'Dataset dimostrativo',
        };
    }

    private function text(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
