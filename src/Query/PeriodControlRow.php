<?php

declare(strict_types=1);

namespace App\Query;

use DateTimeImmutable;

final readonly class PeriodControlRow
{
    public function __construct(
        public DateTimeImmutable $month,
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
