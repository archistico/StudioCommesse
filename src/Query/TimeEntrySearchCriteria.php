<?php

declare(strict_types=1);

namespace App\Query;

use DateTimeImmutable;

final readonly class TimeEntrySearchCriteria
{
    public function __construct(
        public ?int $projectId = null,
        public ?int $activityId = null,
        public ?int $userId = null,
        public ?DateTimeImmutable $startedFrom = null,
        public ?DateTimeImmutable $startedBefore = null,
        public ?bool $billable = null,
        public int $page = 1,
        public int $perPage = 50,
    ) {
    }
}
