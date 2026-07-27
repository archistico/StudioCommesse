<?php

declare(strict_types=1);

namespace App\Query;

use DateTimeImmutable;

final readonly class MonthlyTimeEntryReportRow
{
    public function __construct(
        public int $entryId,
        public DateTimeImmutable $startedAt,
        public ?DateTimeImmutable $endedAt,
        public int $durationMinutes,
        public bool $billable,
        public int $costCents,
        public string $description,
        public int $userId,
        public string $userName,
        public int $activityId,
        public string $activityTitle,
        public int $projectId,
        public string $projectCode,
        public string $projectName,
    ) {
    }

    public function isRunning(): bool
    {
        return null === $this->endedAt;
    }
}
