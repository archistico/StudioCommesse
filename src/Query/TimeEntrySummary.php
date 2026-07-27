<?php

declare(strict_types=1);

namespace App\Query;

final readonly class TimeEntrySummary
{
    public function __construct(
        public int $totalMinutes,
        public int $entryCount,
        public int $userCount,
        public int $projectCount,
    ) {
    }
}
