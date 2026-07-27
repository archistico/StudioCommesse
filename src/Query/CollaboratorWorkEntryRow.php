<?php

declare(strict_types=1);

namespace App\Query;

use DateTimeImmutable;

final readonly class CollaboratorWorkEntryRow
{
    public function __construct(
        public int $timeEntryId,
        public int $projectId,
        public string $projectCode,
        public string $projectName,
        public int $activityId,
        public string $activityTitle,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $endedAt,
        public int $durationMinutes,
        public bool $billable,
        public ?string $description,
    ) {
    }
}
