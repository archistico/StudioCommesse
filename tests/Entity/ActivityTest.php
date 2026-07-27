<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Activity;
use App\Enum\ActivityStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ActivityTest extends TestCase
{
    public function testCompletionNormalizesProgressAndRemainingEstimate():void{$a=(new Activity())->setProgressPercent(60)->setRemainingEstimatedMinutes(120)->setStatus(ActivityStatus::Completed);self::assertSame(100,$a->getProgressPercent());self::assertSame(0,$a->getRemainingEstimatedMinutes());self::assertNotNull($a->getCompletedAt());}
    public function testInProgressInitializesStart():void{$a=(new Activity())->setStatus(ActivityStatus::InProgress);self::assertNotNull($a->getStartAt());}
    public function testOverdueExcludesClosedActivities():void{$a=(new Activity())->setDueAt(new DateTimeImmutable('2026-01-01'));self::assertTrue($a->isOverdue(new DateTimeImmutable('2026-07-27')));$a->setStatus(ActivityStatus::Completed);self::assertFalse($a->isOverdue(new DateTimeImmutable('2026-07-27')));}
    public function testNonCompletedActivityCannotRemainAtOneHundredPercent():void{$a=(new Activity())->setProgressPercent(100);$a->normalizeWorkflow();self::assertSame(99,$a->getProgressPercent());}
}
