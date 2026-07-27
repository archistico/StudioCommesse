<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Project;
use App\Service\ProjectFinancialSummary;
use PHPUnit\Framework\TestCase;

final class ProjectFinancialSummaryTest extends TestCase
{
    public function testSummaryCalculatesCostCollectionAndMargin(): void
    {
        $summary = new ProjectFinancialSummary(new Project(), 1_000_000, 300_000, 150_000, 600_000);

        self::assertSame(450_000, $summary->getTotalCostCents());
        self::assertSame(400_000, $summary->getRemainingToCollectCents());
        self::assertSame(550_000, $summary->getMarginCents());
        self::assertFalse($summary->isOverBudget());
        self::assertFalse($summary->isFullyCollected());
    }

    public function testSummarySignalsOverBudgetAndNeverReturnsNegativeResidual(): void
    {
        $summary = new ProjectFinancialSummary(new Project(), 100_000, 90_000, 30_000, 125_000);

        self::assertTrue($summary->isOverBudget());
        self::assertTrue($summary->isFullyCollected());
        self::assertSame(0, $summary->getRemainingToCollectCents());
        self::assertSame(-20_000, $summary->getMarginCents());
    }
}
