<?php

declare(strict_types=1);

namespace App\Service;

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
}
