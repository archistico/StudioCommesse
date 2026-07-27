<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;

final readonly class ProjectFinancialSummary
{
    public function __construct(
        public Project $project,
        public int $estimatedAmountCents,
        public int $labourCostCents,
        public int $expenseCostCents,
        public int $paymentsCents,
    ) {
    }

    public function getTotalCostCents(): int
    {
        return $this->labourCostCents + $this->expenseCostCents;
    }

    public function getRemainingToCollectCents(): int
    {
        return max(0, $this->estimatedAmountCents - $this->paymentsCents);
    }

    public function getMarginCents(): int
    {
        return $this->estimatedAmountCents - $this->getTotalCostCents();
    }

    public function isOverBudget(): bool
    {
        return $this->estimatedAmountCents > 0 && $this->getTotalCostCents() > $this->estimatedAmountCents;
    }

    public function isFullyCollected(): bool
    {
        return $this->estimatedAmountCents > 0 && $this->paymentsCents >= $this->estimatedAmountCents;
    }
}
