<?php

declare(strict_types=1);
namespace App\Tests\Entity;
use App\Entity\TimeEntry;use DateTimeImmutable;use PHPUnit\Framework\TestCase;
final class TimeEntryTest extends TestCase
{
 public function testDurationIsCalculatedFromInterval():void{$e=(new TimeEntry())->setStartedAt(new DateTimeImmutable('2026-07-27 09:00'))->setEndedAt(new DateTimeImmutable('2026-07-27 10:32'));self::assertSame(92,$e->getDurationMinutes());}
 public function testRunningDurationUsesProvidedClock():void{$e=(new TimeEntry())->setStartedAt(new DateTimeImmutable('2026-07-27 09:00'));self::assertTrue($e->isRunning());self::assertSame(45,$e->getDurationMinutes(new DateTimeImmutable('2026-07-27 09:45')));}
 public function testStopClosesTimer():void{$e=(new TimeEntry())->setStartedAt(new DateTimeImmutable('2026-07-27 09:00'))->stop(new DateTimeImmutable('2026-07-27 10:00'));self::assertFalse($e->isRunning());self::assertSame(60,$e->getDurationMinutes());}
 public function testCostSnapshotRemainsStableAndCanBeRecalculatedFromSameRate():void{$e=(new TimeEntry())->setStartedAt(new DateTimeImmutable('2026-07-27 09:00'))->setEndedAt(new DateTimeImmutable('2026-07-27 10:32'))->applyRateSnapshot(6000);self::assertSame(6000,$e->getHourlyRateSnapshotCents());self::assertSame(9200,$e->getCostSnapshotCents());$e->setEndedAt(new DateTimeImmutable('2026-07-27 11:00'))->recalculateCostFromSnapshot();self::assertSame(12000,$e->getCostSnapshotCents());}
}
