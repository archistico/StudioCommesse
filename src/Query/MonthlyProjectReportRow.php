<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\ProjectPriority;
use App\Enum\ProjectStatus;
use DateTimeImmutable;

final readonly class MonthlyProjectReportRow
{
    public function __construct(
        public int $projectId,
        public string $code,
        public string $name,
        public string $clientName,
        public string $responsibleName,
        public ProjectStatus $status,
        public ProjectPriority $priority,
        public ?DateTimeImmutable $dueDate,
        public int $activityCount,
        public int $openActivityCount,
        public int $completedActivityCount,
        public int $overdueActivityCount,
        public int $averageProgressPercent,
        public int $remainingMinutes,
        public int $timeEntryCount,
        public int $workedMinutes,
        public int $billableMinutes,
        public int $contributorCount,
        public int $labourCostCents,
        public int $expenseCount,
        public int $expenseCents,
        public int $paymentCount,
        public int $paymentCents,
        public int $attachmentCount,
        public ?DateTimeImmutable $lastMovementAt,
    ) {
    }

    public function getMovementCount(): int
    {
        return $this->timeEntryCount + $this->expenseCount + $this->paymentCount + $this->attachmentCount;
    }

    public function hasMovement(): bool
    {
        return $this->getMovementCount() > 0;
    }

    public function isOverdue(?DateTimeImmutable $today = null): bool
    {
        return null !== $this->dueDate && !$this->status->isClosed() && $this->dueDate < ($today ?? new DateTimeImmutable('today'));
    }
}
