<?php

declare(strict_types=1);

namespace App\Query;

use DateTimeImmutable;

final readonly class CollaboratorReportEntry
{
    public function __construct(
        public int $id,
        public int $userId,
        public int $projectId,
        public string $projectCode,
        public string $projectName,
        public bool $archived,
        public int $activityId,
        public string $activityTitle,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
        public ?string $description,
        public bool $billable,
        public int $minutes,
        public int $costCents,
    ) {
    }
}
