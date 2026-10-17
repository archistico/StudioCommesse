<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\ProjectStatus;
use App\Service\TimerService;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\DataProvider;

final class TimerServiceRegressionTest extends DatabaseWebTestCase
{
    #[DataProvider('overlappingStartTimes')]
    public function testTimerStartCannotOverlapACompletedEntry(string $start): void
    {
        $user = $this->createUser('timer-sovrapposto');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $this->createTestTimeEntry($activity, $user, '2026-07-20 10:00', '2026-07-20 11:00');
        $timer = self::getContainer()->get(TimerService::class);
        self::assertInstanceOf(TimerService::class, $timer);

        try {
            $timer->start($activity, $user, new DateTimeImmutable($start));
            self::fail('Il timer sovrapposto deve essere rifiutato.');
        } catch (DomainException $exception) {
            self::assertStringContainsString('sovrappone', $exception->getMessage());
        }

        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM time_entry WHERE ended_at IS NULL'));
    }

    /** @return iterable<string, array{string}> */
    public static function overlappingStartTimes(): iterable
    {
        yield 'durante una registrazione' => ['2026-07-20 10:30'];
        yield 'prima di una registrazione futura' => ['2026-07-20 09:00'];
    }

    public function testTimerCannotBeStartedForAnArchivedProjectThroughTheService(): void
    {
        $user = $this->createUser('timer-archivio');
        $project = $this->createProject($this->createCustomer(), $user, status: ProjectStatus::Completed);
        $activity = $this->createTestActivity($project, $user);
        $project->archive();
        $this->entityManager->flush();
        $timer = self::getContainer()->get(TimerService::class);
        self::assertInstanceOf(TimerService::class, $timer);

        $this->expectException(DomainException::class);
        $timer->start($activity, $user, new DateTimeImmutable('2026-07-20 09:00'));
    }

    public function testAdjacentTimerKeepsItsOriginalRateWhenStopped(): void
    {
        $user = $this->createUser('timer-adiacente');
        $project = $this->createProject($this->createCustomer(), $user);
        $project->setDefaultHourlyRateCents(6_000);
        $firstActivity = $this->createTestActivity($project, $user, 'Prima attività');
        $nextActivity = $this->createTestActivity($project, $user, 'Attività successiva');
        $this->createTestTimeEntry($firstActivity, $user, '2026-07-20 10:00', '2026-07-20 11:00');
        $timer = self::getContainer()->get(TimerService::class);
        self::assertInstanceOf(TimerService::class, $timer);

        $entry = $timer->start($nextActivity, $user, new DateTimeImmutable('2026-07-20 11:00'));
        self::assertTrue($entry->isRunning());
        self::assertSame(0, $entry->getCostSnapshotCents());
        $entryId = $entry->getId();
        $project->setDefaultHourlyRateCents(9_000);
        $this->entityManager->flush();
        $stopped = $timer->stop($user, new DateTimeImmutable('2026-07-20 12:00'));

        self::assertSame($entryId, $stopped->getId());
        self::assertFalse($stopped->isRunning());
        self::assertSame(60, $stopped->getDurationMinutes());
        self::assertSame(6_000, $stopped->getHourlyRateSnapshotCents());
        self::assertSame(6_000, $stopped->getCostSnapshotCents());
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM time_entry WHERE ended_at IS NULL'));
    }

    public function testEntriesOfAnotherUserDoNotBlockTheTimer(): void
    {
        $user = $this->createUser('timer-personale');
        $other = $this->createUser('timer-altra-persona');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $this->createTestTimeEntry($activity, $other, '2026-07-20 10:00', '2026-07-20 11:00');
        $timer = self::getContainer()->get(TimerService::class);
        self::assertInstanceOf(TimerService::class, $timer);

        $entry = $timer->start($activity, $user, new DateTimeImmutable('2026-07-20 10:30'));

        self::assertTrue($entry->isRunning());
        self::assertSame($user->getId(), $entry->getUser()?->getId());
    }

    public function testAnExistingTimerPreventsASecondTimer(): void
    {
        $user = $this->createUser('timer-unico');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $timer = self::getContainer()->get(TimerService::class);
        self::assertInstanceOf(TimerService::class, $timer);
        $timer->start($activity, $user, new DateTimeImmutable('2026-07-20 10:00'));

        try {
            $timer->start($activity, $user, new DateTimeImmutable('2026-07-20 11:00'));
            self::fail('Il secondo timer deve essere rifiutato.');
        } catch (DomainException $exception) {
            self::assertSame('Esiste già un timer attivo.', $exception->getMessage());
        }

        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM time_entry WHERE ended_at IS NULL'));
    }

    public function testTooShortTimerRemainsOpenAfterARejectedStop(): void
    {
        $user = $this->createUser('timer-breve');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $timer = self::getContainer()->get(TimerService::class);
        self::assertInstanceOf(TimerService::class, $timer);
        $timer->start($activity, $user, new DateTimeImmutable('2026-07-20 10:00'));

        try {
            $timer->stop($user, new DateTimeImmutable('2026-07-20 10:00:30'));
            self::fail('Il timer troppo breve deve restare aperto.');
        } catch (DomainException $exception) {
            self::assertSame('Il timer deve durare almeno un minuto.', $exception->getMessage());
        }

        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM time_entry WHERE ended_at IS NULL AND cost_snapshot_cents = 0'));
    }
}
