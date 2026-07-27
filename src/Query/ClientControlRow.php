<?php

declare(strict_types=1);

namespace App\Query;

final readonly class ClientControlRow
{
    public function __construct(
        public int $clientId,
        public string $clientName,
        public int $projectCount,
        public int $openProjectCount,
        public int $closedProjectCount,
        public int $criticalProjectCount,
        public int $workedMinutes,
        public int $billableMinutes,
        public int $labourCostCents,
        public int $expenseCents,
        public int $paymentCents,
    ) {
    }

    public function getPeriodBalanceCents(): int
    {
        return $this->paymentCents - $this->labourCostCents - $this->expenseCents;
    }
}
