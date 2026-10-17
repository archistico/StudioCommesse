<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\TimeEntry;
use App\Enum\ProjectStatus;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

final class TimeEntryWorkflowRegressionTest extends DatabaseWebTestCase
{
    #[DataProvider('missingDateFields')]
    public function testManualEntriesRequireBothDates(bool $editing, string $field): void
    {
        $user = $this->createUser('ore-date-obbligatorie');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $entry = $editing ? $this->createTestTimeEntry($activity, $user, '2026-07-20 09:00', '2026-07-20 10:00') : null;
        $entryId = $entry?->getId();
        $this->client->loginUser($user);

        $path = $editing ? '/ore/'.$entryId.'/modifica' : '/ore/attivita/'.$activity->getId().'/nuova';
        $crawler = $this->client->request('GET', $path);
        $values = $crawler->selectButton('Salva')->form([
            'time_entry[startedAt]' => '2026-07-20T09:00',
            'time_entry[endedAt]' => '2026-07-20T10:00',
            'time_entry[description]' => 'Intervallo incompleto',
        ])->getPhpValues();
        $values['time_entry'][$field] = '';
        $this->client->request('POST', $path, $values);

        self::assertResponseStatusCodeSame(422);
        $this->entityManager->clear();
        self::assertSame($editing ? 1 : 0, $this->entityManager->getRepository(TimeEntry::class)->count([]));
        if ($editing) {
            $saved = $this->entityManager->find(TimeEntry::class, $entryId);
            self::assertInstanceOf(TimeEntry::class, $saved);
            self::assertSame('2026-07-20 09:00:00', $saved->getStartedAt()->format('Y-m-d H:i:s'));
            self::assertSame('2026-07-20 10:00:00', $saved->getEndedAt()?->format('Y-m-d H:i:s'));
        }
    }

    /** @return iterable<string, array{bool, string}> */
    public static function missingDateFields(): iterable
    {
        yield 'nuovo senza inizio' => [false, 'startedAt'];
        yield 'nuovo senza fine' => [false, 'endedAt'];
        yield 'modifica senza inizio' => [true, 'startedAt'];
        yield 'modifica senza fine' => [true, 'endedAt'];
    }

    #[DataProvider('historicalRates')]
    public function testEditingKeepsTheHistoricalRateIncludingZero(int $rate): void
    {
        $user = $this->createUser('ore-tariffa-storica');
        $project = $this->createProject($this->createCustomer(), $user);
        $project->setDefaultHourlyRateCents(8_000);
        $activity = $this->createTestActivity($project, $user);
        $entry = $this->createTestTimeEntry($activity, $user, '2026-07-20 09:00', '2026-07-20 10:00');
        $entry->applyRateSnapshot($rate);
        $this->entityManager->flush();
        $entryId = $entry->getId();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/ore/'.$entryId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva')->form([
            'time_entry[description]' => 'Descrizione aggiornata',
            'time_entry[endedAt]' => '2026-07-20T11:00',
        ]));

        self::assertResponseRedirects('/ore/attivita/'.$activity->getId());
        $this->entityManager->clear();
        $saved = $this->entityManager->find(TimeEntry::class, $entryId);
        self::assertInstanceOf(TimeEntry::class, $saved);
        self::assertSame($rate, $saved->getHourlyRateSnapshotCents());
        self::assertSame(2 * $rate, $saved->getCostSnapshotCents());
    }

    /** @return iterable<string, array{int}> */
    public static function historicalRates(): iterable
    {
        yield 'tariffa zero' => [0];
        yield 'tariffa positiva' => [5_000];
    }

    public function testEditingDescriptionPreservesTimerSecondsDurationAndCost(): void
    {
        $user = $this->createUser('ore-precisione-timer');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $entry = $this->createTestTimeEntry($activity, $user, '2026-07-20 09:00:50', '2026-07-20 10:01:10');
        $entryId = $entry->getId();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/ore/'.$entryId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva')->form([
            'time_entry[description]' => 'Solo testo aggiornato',
        ]));

        self::assertResponseRedirects('/ore/attivita/'.$activity->getId());
        $this->entityManager->clear();
        $saved = $this->entityManager->find(TimeEntry::class, $entryId);
        self::assertInstanceOf(TimeEntry::class, $saved);
        self::assertSame('2026-07-20 09:00:50', $saved->getStartedAt()->format('Y-m-d H:i:s'));
        self::assertSame('2026-07-20 10:01:10', $saved->getEndedAt()?->format('Y-m-d H:i:s'));
        self::assertSame(60, $saved->getDurationMinutes());
        self::assertSame(5_000, $saved->getCostSnapshotCents());
    }

    public function testArchivedHoursAreReadableWithoutEditLinks(): void
    {
        $user = $this->createUser('ore-archiviate');
        $project = $this->createProject($this->createCustomer(), $user, status: ProjectStatus::Completed);
        $activity = $this->createTestActivity($project, $user);
        $entry = $this->createTestTimeEntry($activity, $user, '2026-07-20 09:00', '2026-07-20 10:00', 'Ore archiviate consultabili');
        $project->archive();
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/ore');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Ore archiviate consultabili');
        self::assertSelectorNotExists('a[href="/ore/'.$entry->getId().'/modifica"]');
    }

    public function testTimerOverlapShowsAnErrorWithoutCreatingARunningEntry(): void
    {
        $user = $this->createUser('ore-timer-sovrapposto');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $this->createTestTimeEntry(
            $activity,
            $user,
            (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s'),
            (new DateTimeImmutable('+2 hours'))->format('Y-m-d H:i:s'),
        );
        $activityId = $activity->getId();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/ore/attivita/'.$activityId);
        $this->client->submit($crawler->selectButton('Avvia timer')->form());
        self::assertResponseRedirects('/ore/attivita/'.$activityId);
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-danger', 'L’intervallo si sovrappone a un’altra registrazione.');
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM time_entry WHERE ended_at IS NULL'));
    }

    #[DataProvider('manualSaveModes')]
    public function testManualOverlapsAreRejectedAndAdjacentEntriesAreAccepted(bool $editing): void
    {
        $user = $this->createUser('ore-sovrapposizioni');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $this->createTestTimeEntry($activity, $user, '2026-07-20 09:00', '2026-07-20 10:00');
        $entry = $editing ? $this->createTestTimeEntry($activity, $user, '2026-07-20 10:00', '2026-07-20 11:00') : null;
        $entryId = $entry?->getId();
        $activityId = $activity->getId();
        $this->client->loginUser($user);
        $path = $editing ? '/ore/'.$entryId.'/modifica' : '/ore/attivita/'.$activityId.'/nuova';

        $crawler = $this->client->request('GET', $path);
        $this->client->submit($crawler->selectButton('Salva')->form([
            'time_entry[startedAt]' => '2026-07-20T09:30',
            'time_entry[endedAt]' => '2026-07-20T11:00',
        ]));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'L’intervallo si sovrappone a un’altra registrazione.');

        $crawler = $this->client->request('GET', $path);
        $this->client->submit($crawler->selectButton('Salva')->form([
            'time_entry[startedAt]' => '2026-07-20T10:00',
            'time_entry[endedAt]' => '2026-07-20T11:00',
        ]));
        self::assertResponseRedirects('/ore/attivita/'.$activityId);
        $this->entityManager->clear();
        self::assertSame(2, $this->entityManager->getRepository(TimeEntry::class)->count([]));
    }

    /** @return iterable<string, array{bool}> */
    public static function manualSaveModes(): iterable
    {
        yield 'creazione' => [false];
        yield 'modifica' => [true];
    }
}
