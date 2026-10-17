<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\TimeEntry;
use App\Enum\AuditAction;
use App\Enum\ProjectStatus;
use App\Service\TimerService;
use App\Tests\DatabaseWebTestCase;

final class TimerCancellationTest extends DatabaseWebTestCase
{
    public function testImmediateStopOffersCancellationAndDoesNothingUntilConfirmed(): void
    {
        $user = $this->createUser('conferma-timer');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $activityUrl = '/ore/attivita/'.$activity->getId();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', $activityUrl);
        $this->client->submit($crawler->selectButton('Avvia timer')->form());
        $crawler = $this->client->followRedirect();
        $entry = $this->entityManager->getRepository(TimeEntry::class)->findOneBy(['user' => $user, 'endedAt' => null]);
        self::assertInstanceOf(TimeEntry::class, $entry);
        $entryId = $entry->getId();
        $this->client->submit($crawler->selectButton('Ferma timer')->form());

        self::assertResponseRedirects($activityUrl.'?cancel_timer='.$entryId);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('[data-timer-cancel-prompt]', 'Il timer deve durare almeno un minuto.');
        self::assertSelectorTextContains('[data-timer-cancel-prompt]', 'Se non fai nulla, il timer continua.');
        self::assertSelectorNotExists('button:contains("Ferma timer")');
        self::assertNull($this->entityManager->find(TimeEntry::class, $entryId)?->getEndedAt());
        self::assertSame(0, $this->entityManager->getRepository(AuditLog::class)->count(['action' => AuditAction::TimerStopped]));
        $this->exportPreview('timer-conferma');

        $this->client->click($crawler->selectLink('Lascia continuare')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-timer-cancel-prompt]');
        self::assertSelectorExists('button:contains("Ferma timer")');
        self::assertNull($this->entityManager->find(TimeEntry::class, $entryId)?->getEndedAt());

        $crawler = $this->client->request('GET', $activityUrl.'?cancel_timer='.$entryId);
        $this->client->submit($crawler->selectButton('Annulla registrazione')->form());
        self::assertResponseRedirects($activityUrl);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(TimeEntry::class, $entryId));
        $audit = $this->entityManager->getRepository(AuditLog::class)->findOneBy(['action' => AuditAction::TimerCancelled]);
        self::assertInstanceOf(AuditLog::class, $audit);
        self::assertSame($entryId, $audit->getSubjectId());
        self::assertSame($activity->getId(), $audit->getDetails()['activity_id']);
        $events = self::getContainer()->get(\App\Repository\MonthlyReportRepository::class)->findActionEvents(
            new \DateTimeImmutable('first day of this month midnight'),
            new \DateTimeImmutable('first day of next month midnight'),
            $project->getId(),
        );
        self::assertContains(AuditAction::TimerCancelled->value, array_column($events, 'action'));
    }

    public function testNormalStopStillSavesMinutesAndSnapshotCost(): void
    {
        $user = $this->createUser('timer-durata-valida');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $entry = $this->createTestTimeEntry($activity, $user, '-2 minutes', null);
        $entryId = $entry->getId();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/ore/attivita/'.$activity->getId());
        $this->client->submit($crawler->selectButton('Ferma timer')->form());
        self::assertResponseRedirects('/ore/attivita/'.$activity->getId());
        $entry = $this->entityManager->find(TimeEntry::class, $entryId);
        self::assertInstanceOf(TimeEntry::class, $entry);
        self::assertFalse($entry->isRunning());
        self::assertGreaterThanOrEqual(2, $entry->getDurationMinutes());
        self::assertGreaterThan(0, $entry->getCostSnapshotCents());
        self::assertSame(0, $this->entityManager->getRepository(AuditLog::class)->count(['action' => AuditAction::TimerCancelled]));
    }

    public function testCancellationRequiresItsOwnCsrfTokenAndNeverDeletesAnotherUsersEntry(): void
    {
        $user = $this->createUser('timer-proprietario');
        $other = $this->createUser('timer-altro-utente');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $entry = $this->createTestTimeEntry($activity, $user, 'now', null);
        $foreign = $this->createTestTimeEntry($activity, $other, 'now', null);
        $entryId = $entry->getId();
        $foreignId = $foreign->getId();
        $this->client->loginUser($user);
        $normal = $this->client->request('GET', '/ore/attivita/'.$activity->getId());
        $stopValues = $normal->selectButton('Ferma timer')->form()->getPhpValues();
        $crawler = $this->client->request('GET', '/ore/attivita/'.$activity->getId().'?cancel_timer='.$entryId);
        $form = $crawler->selectButton('Annulla registrazione')->form();
        $cancelValues = $form->getPhpValues();
        $this->client->request('POST', '/ore/timer/ferma', ['intent' => 'cancel', 'entry_id' => $entryId, '_token' => 'errato']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/ore/timer/ferma', ['intent' => 'cancel', 'entry_id' => $entryId, '_token' => $stopValues['_token']]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/ore/timer/ferma', array_replace($cancelValues, ['entry_id' => $foreignId]));
        self::assertResponseStatusCodeSame(403);
        $this->entityManager->clear();
        self::assertNotNull($this->entityManager->find(TimeEntry::class, $entryId));
        self::assertNotNull($this->entityManager->find(TimeEntry::class, $foreignId));
        self::assertSame(0, $this->entityManager->getRepository(AuditLog::class)->count(['action' => AuditAction::TimerCancelled]));
        $this->client->request('GET', '/ore/attivita/'.$activity->getId().'?cancel_timer='.$foreignId);
        self::assertSelectorNotExists('[data-timer-cancel-prompt]');
    }

    public function testStaleConfirmationCannotCancelAReplacementTimer(): void
    {
        $user = $this->createUser('timer-conferma-scaduta');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $timer = self::getContainer()->get(TimerService::class);
        $old = $timer->start($activity, $user);
        $oldId = $old->getId();
        self::assertIsInt($oldId);
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/ore/attivita/'.$activity->getId().'?cancel_timer='.$oldId);
        $form = $crawler->selectButton('Annulla registrazione')->form();
        $timer->cancel($user, $oldId);
        $replacement = $timer->start($activity, $user);
        $replacementId = $replacement->getId();
        $this->client->submit($form);
        self::assertResponseRedirects('/attivita');
        $this->entityManager->clear();
        $saved = $this->entityManager->find(TimeEntry::class, $replacementId);
        self::assertInstanceOf(TimeEntry::class, $saved);
        self::assertTrue($saved->isRunning());
        self::assertSame(1, $this->entityManager->getRepository(AuditLog::class)->count(['action' => AuditAction::TimerCancelled]));
    }

    public function testConfirmedCancellationStillWorksAfterTheMinuteHasElapsed(): void
    {
        $user = $this->createUser('timer-scelta-tardiva');
        $project = $this->createProject($this->createCustomer(), $user);
        $activity = $this->createTestActivity($project, $user);
        $entry = $this->createTestTimeEntry($activity, $user, '-2 minutes', null);
        $entryId = $entry->getId();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/ore/attivita/'.$activity->getId().'?cancel_timer='.$entryId);
        $this->client->submit($crawler->selectButton('Annulla registrazione')->form());
        self::assertResponseRedirects('/ore/attivita/'.$activity->getId());
        self::assertSame(0, $this->entityManager->getRepository(TimeEntry::class)->count([]));
    }

    public function testArchivedTimerCannotBeCancelled(): void
    {
        $user = $this->createUser('timer-cancellazione-archivio');
        $project = $this->createProject($this->createCustomer(), $user, status: ProjectStatus::Completed);
        $activity = $this->createTestActivity($project, $user);
        $entry = $this->createTestTimeEntry($activity, $user, 'now', null);
        $entryId = $entry->getId();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/ore/attivita/'.$activity->getId().'?cancel_timer='.$entryId);
        $form = $crawler->selectButton('Annulla registrazione')->form();
        $managedProject = $this->entityManager->find(\App\Entity\Project::class, $project->getId());
        self::assertNotNull($managedProject);
        $managedProject->archive();
        $this->entityManager->flush();
        $this->client->submit($form);
        self::assertResponseRedirects('/attivita');
        self::assertNotNull($this->entityManager->find(TimeEntry::class, $entryId));
    }

    private function exportPreview(string $name): void
    {
        if ('1' === getenv('STUDIO_UI_PREVIEW')) {
            $directory = dirname(__DIR__, 2).'/var/ui-preview';
            if (!is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($directory.'/'.$name.'.html', (string) $this->client->getResponse()->getContent());
        }
    }
}
