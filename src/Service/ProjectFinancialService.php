<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Client;
use App\Entity\Project;
use App\Repository\ExpenseRepository;
use App\Repository\PaymentRepository;
use App\Repository\TimeEntryRepository;

final readonly class ProjectFinancialService
{
    public function __construct(
        private TimeEntryRepository $timeEntries,
        private ExpenseRepository $expenses,
        private PaymentRepository $payments,
    ) {
    }

    public function summarize(Project $project): ProjectFinancialSummary
    {
        $summaries = $this->summarizeMany([$project]);
        $summary = $summaries[0] ?? null;
        if (!$summary instanceof ProjectFinancialSummary) {
            throw new \LogicException('Riepilogo economico non disponibile.');
        }

        return $summary;
    }

    /**
     * Calcola tutti i riepiloghi con tre query aggregate complessive,
     * indipendentemente dal numero di commesse.
     *
     * @param list<Project> $projects
     * @return list<ProjectFinancialSummary>
     */
    public function summarizeMany(array $projects): array
    {
        $projectIds = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            if (null !== $id) {
                $projectIds[] = $id;
            }
        }

        $labourCosts = $this->timeEntries->sumCostCentsByProjectIds($projectIds);
        $expenseCosts = $this->expenses->sumCentsByProjectIds($projectIds);
        $payments = $this->payments->sumCentsByProjectIds($projectIds);

        $summaries = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            $summaries[] = new ProjectFinancialSummary(
                project: $project,
                estimatedAmountCents: $project->getEstimatedAmountCents(),
                labourCostCents: null === $id ? 0 : ($labourCosts[$id] ?? 0),
                expenseCostCents: null === $id ? 0 : ($expenseCosts[$id] ?? 0),
                paymentsCents: null === $id ? 0 : ($payments[$id] ?? 0),
            );
        }

        return $summaries;
    }

    /**
     * @param list<ProjectFinancialSummary> $summaries
     * @return list<ClientFinancialSummary>
     */
    public function summarizeByClient(array $summaries): array
    {
        /** @var array<int, array{client: Client, project_count: int, estimated: int, payments: int, remaining: int, unconfigured: int}> $grouped */
        $grouped = [];

        foreach ($summaries as $summary) {
            $client = $summary->project->getClient();
            $clientId = $client?->getId();
            if (!$client instanceof Client || null === $clientId) {
                continue;
            }

            $grouped[$clientId] ??= [
                'client' => $client,
                'project_count' => 0,
                'estimated' => 0,
                'payments' => 0,
                'remaining' => 0,
                'unconfigured' => 0,
            ];
            ++$grouped[$clientId]['project_count'];
            $grouped[$clientId]['estimated'] += $summary->estimatedAmountCents;
            $grouped[$clientId]['payments'] += $summary->paymentsCents;
            $grouped[$clientId]['remaining'] += $summary->getRemainingToCollectCents();
            if ($summary->estimatedAmountCents <= 0) {
                ++$grouped[$clientId]['unconfigured'];
            }
        }

        $rows = array_map(
            static fn (array $row): ClientFinancialSummary => new ClientFinancialSummary(
                client: $row['client'],
                projectCount: $row['project_count'],
                estimatedAmountCents: $row['estimated'],
                paymentsCents: $row['payments'],
                remainingToCollectCents: $row['remaining'],
                unconfiguredProjectCount: $row['unconfigured'],
            ),
            array_values($grouped),
        );
        usort($rows, static fn (ClientFinancialSummary $left, ClientFinancialSummary $right): int =>
            [$right->remainingToCollectCents, $left->client->getName()]
            <=> [$left->remainingToCollectCents, $right->client->getName()]
        );

        return $rows;
    }
}
