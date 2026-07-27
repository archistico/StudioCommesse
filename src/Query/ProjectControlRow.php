<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\EconomicClosureStatus;
use App\Enum\OperationalClosureStatus;
use App\Enum\OverallClosureStatus;
use App\Enum\ProjectStatus;
use DateTimeImmutable;

final readonly class ProjectControlRow
{
    /**
     * @param list<string> $alerts
     */
    public function __construct(
        public int $projectId,
        public string $code,
        public string $name,
        public int $clientId,
        public string $clientName,
        public int $responsibleId,
        public string $responsibleName,
        public ProjectStatus $projectStatus,
        public ?DateTimeImmutable $dueDate,
        public int $activityCount,
        public int $openActivityCount,
        public int $runningTimerCount,
        public int $estimatedMinutes,
        public int $remainingMinutes,
        public int $actualMinutes,
        public int $estimatedAmountCents,
        public int $labourCostCents,
        public int $expenseCostCents,
        public int $paymentsCents,
        public DateTimeImmutable $lastOperationalAt,
        public bool $overdue,
        public bool $stalled,
        public OperationalClosureStatus $operationalStatus,
        public EconomicClosureStatus $economicStatus,
        public OverallClosureStatus $overallStatus,
        public array $alerts,
    ) {
    }

    public function getTotalCostCents(): int
    {
        return $this->labourCostCents + $this->expenseCostCents;
    }

    public function getRemainingToCollectCents(): int
    {
        return max(0, $this->estimatedAmountCents - $this->paymentsCents);
    }

    public function getMarginCents(): int
    {
        return $this->estimatedAmountCents - $this->getTotalCostCents();
    }

    public function getTimeDeviationMinutes(): int
    {
        return $this->actualMinutes - $this->estimatedMinutes;
    }

    public function getTimeDeviationPercent(): ?float
    {
        if ($this->estimatedMinutes <= 0) {
            return null;
        }

        return ($this->actualMinutes - $this->estimatedMinutes) * 100 / $this->estimatedMinutes;
    }

    public function isOverBudget(): bool
    {
        return $this->estimatedAmountCents > 0 && $this->getTotalCostCents() > $this->estimatedAmountCents;
    }

    public function isTimeOverrun(): bool
    {
        return $this->estimatedMinutes > 0 && $this->actualMinutes > $this->estimatedMinutes;
    }

    public function isCritical(): bool
    {
        return [] !== $this->alerts;
    }

    public function getCriticalityScore(): int
    {
        $score = count($this->alerts) * 10;
        $score += $this->overdue ? 8 : 0;
        $score += $this->stalled ? 6 : 0;
        $score += $this->isOverBudget() ? 5 : 0;
        $score += $this->isTimeOverrun() ? 4 : 0;
        $score += $this->runningTimerCount > 0 ? 3 : 0;

        return $score;
    }
}
