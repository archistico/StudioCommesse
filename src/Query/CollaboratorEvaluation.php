<?php

declare(strict_types=1);

namespace App\Query;

use DateTimeImmutable;

final readonly class CollaboratorEvaluation
{
    /** @param list<CollaboratorWorkDayRow> $days */
    public function __construct(
        public int $userId,
        public string $displayName,
        public string $roleLabel,
        public bool $active,
        public DateTimeImmutable $periodFrom,
        public DateTimeImmutable $periodTo,
        public int $totalMinutes,
        public int $billableMinutes,
        public int $entryCount,
        public int $projectCount,
        public int $workedDayCount,
        public int $averageMinutesPerWorkedDay,
        public array $days,
    ) {
    }
}
