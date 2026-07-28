<?php

declare(strict_types=1);

namespace App\Query;

final readonly class MonthlyUserCostReportRow
{
    public function __construct(
        public int $userId,
        public string $userName,
        public bool $active,
        public int $timeEntryCount,
        public int $workedMinutes,
        public int $standardHourlyRateCents,
        public ?int $standardCostCents,
        public int $historicalCostCents,
        public ?int $varianceCents,
    ) {
    }

    public function hasStandardRate(): bool
    {
        return $this->standardHourlyRateCents > 0;
    }
}
