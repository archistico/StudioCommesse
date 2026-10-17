<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\ActivityStatus;
use DateTimeImmutable;

final readonly class CollaboratorReportActivity
{
    public function __construct(
        public int $id,
        public int $userId,
        public int $projectId,
        public string $projectCode,
        public string $projectName,
        public string $title,
        public ActivityStatus $status,
        public int $progress,
        public ?DateTimeImmutable $dueAt,
        public ?DateTimeImmutable $completedAt,
        public ?int $estimatedMinutes,
        public ?int $remainingMinutes,
        public int $actualMinutes,
        public bool $overdue,
    ) {
    }

    public function missingEstimate(): bool
    {
        return null === $this->estimatedMinutes || null === $this->remainingMinutes;
    }
}
