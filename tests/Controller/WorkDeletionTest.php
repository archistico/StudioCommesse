<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Activity;
use App\Entity\Attachment;
use App\Entity\AuditLog;
use App\Entity\Expense;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Enum\AttachmentClassification;
use App\Enum\AuditAction;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Repository\TimeEntryRepository;
use App\Service\WorkDeletionService;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class WorkDeletionTest extends DatabaseWebTestCase
{
    public function testPartnerDeletesAnotherUsersHoursOnlyAfterConfirmation(): void
    {
        $partner = $this->createUser('socio-elimina-ore', UserRole::Partner);
        $owner = $this->createUser('autore-ore');
        $project = $this->createProject($this->createCustomer(), $owner);
        $activity = $this->createTestActivity($project, $owner);
        $deleted = $this->createTestTimeEntry($activity, $owner, '2026-10-01 09:00', '2026-10-01 10:00');
        $remaining = $this->createTestTimeEntry($activity, $owner, '2026-10-01 10:00', '2026-10-01 11:30');
        $deletedId = $deleted->getId();
        $remainingId = $remaining->getId();
        $activityId = $activity->getId();
        $this->client->loginUser($partner);

        $this->client->request('GET', '/ore/'.$deletedId.'/modifica');
        self::assertSelectorExists('a[href="/ore/'.$deletedId.'/elimina"]');
        $crawler = $this->client->request('GET', '/ore/'.$deletedId.'/elimina');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-page', $owner->getDisplayName());
        self::assertSame(2, $this->entityManager->getRepository(TimeEntry::class)->count([]));
        $this->exportPreview('elimina-ore');
        $this->client->click($crawler->selectLink('Annulla')->link());
        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->entityManager->getRepository(TimeEntry::class)->count([]));
        $crawler = $this->client->request('GET', '/ore/'.$deletedId.'/elimina');
        $this->client->submit($crawler->selectButton('Elimina registrazione ore')->form());

        self::assertResponseRedirects('/ore/attivita/'.$activityId);
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(TimeEntry::class, $deletedId));
        self::assertNotNull($this->entityManager->find(TimeEntry::class, $remainingId));
        self::assertNotNull($this->entityManager->find(Activity::class, $activityId));
        self::assertSame(90, self::getContainer()->get(TimeEntryRepository::class)->sumMinutesForActivity($activity));
        $audit = $this->entityManager->getRepository(AuditLog::class)->findOneBy(['action' => AuditAction::TimeEntryDeleted]);
        self::assertInstanceOf(AuditLog::class, $audit);
        self::assertSame($deletedId, $audit->getSubjectId());
        self::assertSame($partner->getUserIdentifier(), $audit->getActorIdentifier());
        self::assertSame($owner->getId(), $audit->getDetails()['user_id']);
        self::assertSame($project->getId(), $audit->getDetails()['project_id']);
        self::assertSame(60, $audit->getDetails()['minutes']);
        $this->client->request('GET', '/ore/'.$deletedId.'/elimina');
        self::assertResponseStatusCodeSame(404);
    }

    public function testDeletingActivityRemovesAllUsersHoursAndTimersButKeepsExpensesAndDocuments(): void
    {
        $partner = $this->createUser('socio-elimina-attivita', UserRole::Partner);
        $first = $this->createUser('prima-persona');
        $second = $this->createUser('seconda-persona');
        $project = $this->createProject($this->createCustomer(), $first);
        $activity = $this->createTestActivity($project, $first, 'Attività da eliminare');
        $otherActivity = $this->createTestActivity($project, $second, 'Attività da conservare');
        $this->createTestTimeEntry($activity, $first, '2026-10-01 09:00', '2026-10-01 10:00');
        $this->createTestTimeEntry($activity, $second, '2026-10-01 10:00', '2026-10-01 11:30');
        $running = $this->createTestTimeEntry($activity, $first, 'now', null);
        $remaining = $this->createTestTimeEntry($otherActivity, $second, '2026-10-02 09:00', '2026-10-02 10:00');
        $expense = (new Expense())->setProject($project)->setActivity($activity)->setRecordedBy($first)->setDescription('Spesa da conservare')->setAmountCents(2000);
        $attachment = new Attachment($project, $activity, $first, AttachmentClassification::Technical, 'conservare.pdf', 'test/conservare.pdf', 'application/pdf', 50, str_repeat('a', 64), 'Documento da conservare');
        $this->entityManager->persist($expense);
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();
        $activityId = $activity->getId();
        $runningId = $running->getId();
        $remainingId = $remaining->getId();
        $expenseId = $expense->getId();
        $attachmentId = $attachment->getId();
        $this->client->loginUser($partner);
        $this->client->request('GET', '/attivita/'.$activityId.'/modifica');
        self::assertSelectorExists('a[href="/attivita/'.$activityId.'/elimina"]');
        $crawler = $this->client->request('GET', '/attivita/'.$activityId.'/elimina');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.form-page', '3 registrazioni ore');
        self::assertSelectorTextContains('.form-page', '2:30');
        self::assertSelectorTextContains('.alert-warning', '1 timer attivo');
        self::assertSelectorTextContains('.form-page', '1 spesa e 1 documento');
        self::assertSame(4, $this->entityManager->getRepository(TimeEntry::class)->count([]));
        $this->exportPreview('elimina-attivita');
        $this->client->submit($crawler->selectButton('Elimina attività e tutte le ore')->form());

        self::assertResponseRedirects('/commesse/'.$project->getId());
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Activity::class, $activityId));
        self::assertNull($this->entityManager->find(TimeEntry::class, $runningId));
        self::assertSame(1, $this->entityManager->getRepository(TimeEntry::class)->count([]));
        self::assertNotNull($this->entityManager->find(TimeEntry::class, $remainingId));
        $savedExpense = $this->entityManager->find(Expense::class, $expenseId);
        $savedAttachment = $this->entityManager->find(Attachment::class, $attachmentId);
        self::assertInstanceOf(Expense::class, $savedExpense);
        self::assertInstanceOf(Attachment::class, $savedAttachment);
        self::assertNull($savedExpense->getActivity());
        self::assertNull($savedAttachment->getActivity());
        self::assertSame($project->getId(), $savedExpense->getProject()?->getId());
        self::assertSame($project->getId(), $savedAttachment->getProject()->getId());
        self::assertSame(2000, $savedExpense->getAmountCents());
        self::assertSame('test/conservare.pdf', $savedAttachment->getStorageKey());
        $audit = $this->entityManager->getRepository(AuditLog::class)->findOneBy(['action' => AuditAction::ActivityDeleted]);
        self::assertInstanceOf(AuditLog::class, $audit);
        self::assertSame($activityId, $audit->getSubjectId());
        self::assertSame(3, $audit->getDetails()['entry_count']);
        self::assertSame(1, $audit->getDetails()['running_count']);
        self::assertSame(150, $audit->getDetails()['total_minutes']);
        self::assertSame($project->getId(), $audit->getDetails()['project_id']);
    }

    public function testPartnerCanDeleteAnotherUsersActiveTimerWithoutChangingOtherTimers(): void
    {
        $partner = $this->createUser('socio-timer', UserRole::Partner);
        $worker = $this->createUser('autore-timer');
        $project = $this->createProject($this->createCustomer(), $worker);
        $activity = $this->createTestActivity($project, $worker);
        $entry = $this->createTestTimeEntry($activity, $worker, 'now', null);
        $other = $this->createTestTimeEntry($activity, $partner, 'now', null);
        $entryId = $entry->getId();
        $otherId = $other->getId();
        $this->client->loginUser($partner);
        foreach (['/ore', '/ore/attivita/'.$activity->getId()] as $path) {
            $this->client->request('GET', $path);
            self::assertSelectorExists('a[href="/ore/'.$entryId.'/elimina"]');
        }
        $crawler = $this->client->request('GET', '/ore/'.$entryId.'/elimina');
        self::assertSelectorTextContains('.alert-warning', 'timer attivo verrà eliminato');
        $this->exportPreview('elimina-timer-socio');
        $this->client->submit($crawler->selectButton('Elimina registrazione ore')->form());
        self::assertResponseRedirects('/ore/attivita/'.$activity->getId());
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(TimeEntry::class, $entryId));
        $savedOther = $this->entityManager->find(TimeEntry::class, $otherId);
        self::assertInstanceOf(TimeEntry::class, $savedOther);
        self::assertTrue($savedOther->isRunning());
        $audit = $this->entityManager->getRepository(AuditLog::class)->findOneBy(['action' => AuditAction::TimeEntryDeleted]);
        self::assertInstanceOf(AuditLog::class, $audit);
        self::assertTrue($audit->getDetails()['was_running']);
    }

    #[DataProvider('deletionKinds')]
    public function testCollaboratorOwnerAndResponsibleCannotDeleteEvenByDirectPost(string $kind): void
    {
        $owner = $this->createUser('proprietario-senza-eliminazione');
        $project = $this->createProject($this->createCustomer(), $owner);
        $activity = $this->createTestActivity($project, $owner);
        $entry = $this->createTestTimeEntry($activity, $owner, '2026-10-01 09:00', '2026-10-01 10:00');
        $path = 'activity' === $kind ? '/attivita/'.$activity->getId().'/elimina' : '/ore/'.$entry->getId().'/elimina';
        $this->client->loginUser($owner);
        $this->client->request('GET', '/attivita/'.$activity->getId().'/modifica');
        self::assertSelectorNotExists('a[href="/attivita/'.$activity->getId().'/elimina"]');
        $this->client->request('GET', '/ore/'.$entry->getId().'/modifica');
        self::assertSelectorNotExists('a[href="/ore/'.$entry->getId().'/elimina"]');
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, $path, ['_token' => 'non-rilevante']);
            self::assertResponseStatusCodeSame(403);
        }
        self::assertSame(1, $this->entityManager->getRepository(Activity::class)->count([]));
        self::assertSame(1, $this->entityManager->getRepository(TimeEntry::class)->count([]));
    }

    #[DataProvider('deletionKinds')]
    public function testDeletionRequiresTheTokenOfThatExactRecordAndPost(string $kind): void
    {
        $partner = $this->createUser('socio-csrf-eliminazione', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner);
        $first = $this->createTestActivity($project, $partner);
        $second = $this->createTestActivity($project, $partner, 'Altra attività');
        $entry = $this->createTestTimeEntry($first, $partner, '2026-10-01 09:00', '2026-10-01 10:00');
        $otherEntry = $this->createTestTimeEntry($second, $partner, '2026-10-01 10:00', '2026-10-01 11:00');
        $path = 'activity' === $kind ? '/attivita/'.$first->getId().'/elimina' : '/ore/'.$entry->getId().'/elimina';
        $otherPath = 'activity' === $kind ? '/attivita/'.$second->getId().'/elimina' : '/ore/'.$otherEntry->getId().'/elimina';
        $this->client->loginUser($partner);
        $crawler = $this->client->request('GET', $path);
        $values = $crawler->filter('.form-page form')->form()->getPhpValues();
        $this->client->request('POST', $path, ['_token' => 'errato']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $otherPath, $values);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('DELETE', $path);
        self::assertResponseStatusCodeSame(405);
        self::assertSame(2, $this->entityManager->getRepository(Activity::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(TimeEntry::class)->count([]));
    }

    #[DataProvider('deletionKinds')]
    public function testArchivingBetweenConfirmationAndSubmissionPreventsDeletion(string $kind): void
    {
        $partner = $this->createUser('socio-archivio-eliminazione', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner, status: ProjectStatus::Completed);
        $activity = $this->createTestActivity($project, $partner);
        $entry = $this->createTestTimeEntry($activity, $partner, '2026-10-01 09:00', '2026-10-01 10:00');
        $path = 'activity' === $kind ? '/attivita/'.$activity->getId().'/elimina' : '/ore/'.$entry->getId().'/elimina';
        $this->client->loginUser($partner);
        $crawler = $this->client->request('GET', $path);
        $form = $crawler->filter('.form-page form')->form();
        $managed = $this->entityManager->find(Project::class, $project->getId());
        self::assertInstanceOf(Project::class, $managed);
        $managed->archive();
        $this->entityManager->flush();
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->entityManager->getRepository(Activity::class)->count([]));
        self::assertSame(1, $this->entityManager->getRepository(TimeEntry::class)->count([]));
    }

    #[DataProvider('deletionKinds')]
    public function testDeletionAndAuditRollbackTogether(string $kind): void
    {
        $partner = $this->createUser('socio-atomicita', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner);
        $activity = $this->createTestActivity($project, $partner);
        $entry = $this->createTestTimeEntry($activity, $partner, '2026-10-01 09:00', '2026-10-01 10:00');
        $expense = (new Expense())->setProject($project)->setActivity($activity)->setRecordedBy($partner)->setDescription('Spesa da mantenere con audit fallito')->setAmountCents(1000);
        $attachment = new Attachment($project, $activity, $partner, AttachmentClassification::Technical, 'rollback.pdf', 'test/rollback.pdf', 'application/pdf', 50, str_repeat('b', 64));
        $this->entityManager->persist($expense);
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();
        $activityId = $activity->getId();
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("CREATE TRIGGER reject_deletion_audit BEFORE INSERT ON audit_log WHEN NEW.action IN ('activity.deleted', 'time_entry.deleted') BEGIN SELECT RAISE(ABORT, 'forced_audit_failure'); END");
        $deletion = self::getContainer()->get(WorkDeletionService::class);
        try {
            if ('activity' === $kind) {
                $deletion->deleteActivity($activity, $partner);
            } else {
                $deletion->deleteEntry($entry, $partner);
            }
            self::fail('Il fallimento dell’audit deve annullare anche l’eliminazione.');
        } catch (\Doctrine\DBAL\Exception $exception) {
            self::assertStringContainsString('forced_audit_failure', $exception->getMessage());
        }
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM activity'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM time_entry'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM audit_log'));
        self::assertSame($activityId, (int) $connection->fetchOne('SELECT activity_id FROM expense'));
        self::assertSame($activityId, (int) $connection->fetchOne('SELECT activity_id FROM attachment'));
    }

    public function testDeletionServiceAlsoRequiresAnActivePartner(): void
    {
        $collaborator = $this->createUser('servizio-eliminazione-negato');
        $project = $this->createProject($this->createCustomer(), $collaborator);
        $activity = $this->createTestActivity($project, $collaborator);
        $deletion = self::getContainer()->get(WorkDeletionService::class);
        $this->expectException(AccessDeniedException::class);
        $deletion->deleteActivity($activity, $collaborator);
    }

    /** @return iterable<array{string}> */
    public static function deletionKinds(): iterable
    {
        yield 'attività' => ['activity'];
        yield 'registrazione' => ['entry'];
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
