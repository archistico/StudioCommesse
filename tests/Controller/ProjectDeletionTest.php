<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Activity;
use App\Entity\Attachment;
use App\Entity\AuditLog;
use App\Entity\Client;
use App\Entity\Expense;
use App\Entity\Payment;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\AttachmentClassification;
use App\Enum\AuditAction;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Service\ProjectDeletionService;
use App\Tests\DatabaseWebTestCase;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ProjectDeletionTest extends DatabaseWebTestCase
{
    /** @var list<string> */
    private array $fixtureFiles = [];

    public function testPartnerDeletesProjectWithAllLinkedDataAndFilesButKeepsOtherProjectsUsersClientsAndAudit(): void
    {
        $partner = $this->createUser('socio-cancella', UserRole::Partner);
        $worker = $this->createUser('collaboratore-commessa');
        $customer = $this->createCustomer();
        $project = $this->createProject($customer, $worker);
        $other = $this->createProject($customer, $partner, 'Commessa da conservare');
        $activity = $this->createTestActivity($project, $worker);
        $otherActivity = $this->createTestActivity($other, $partner);
        $this->createTestTimeEntry($activity, $worker, '2026-10-01 09:00', '2026-10-01 10:00');
        $this->createTestTimeEntry($activity, $partner, 'now', null);
        $this->createTestTimeEntry($otherActivity, $partner, '2026-10-01 10:00', '2026-10-01 11:00');
        $this->entityManager->persist((new Expense())->setProject($project)->setActivity($activity)->setRecordedBy($worker)->setDescription('Spesa')->setAmountCents(500));
        $this->entityManager->persist((new Expense())->setProject($project)->setRecordedBy($partner)->setDescription('Spesa generale')->setAmountCents(100));
        $this->entityManager->persist((new Payment())->setProject($project)->setRecordedBy($partner)->setAmountCents(1000));
        $deletedFile = $this->attachment($project, $worker, $activity);
        $generalFile = $this->attachment($project, $partner);
        $preservedFile = $this->attachment($other, $partner);
        $this->entityManager->persist(new AuditLog(AuditAction::ProjectCreated, $partner->getUsername(), Project::class, $project->getId()));
        $this->entityManager->flush();
        $id = $project->getId();
        $code = $project->getCode();
        $this->client->loginUser($partner);
        $this->client->request('GET', '/commesse/'.$id.'/modifica');
        self::assertSelectorExists('a.btn-danger[href="/commesse/'.$id.'/elimina"]');
        $crawler = $this->client->request('GET', '/commesse/'.$id.'/elimina');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-warning', '1 timer');
        self::assertSelectorTextContains('.detail-list', '1:00');
        self::assertSelectorExists('.page-heading .page-back[href="/commesse/'.$id.'/modifica"]');
        self::assertSame(2, $this->entityManager->getRepository(Project::class)->count([]));
        self::assertFileExists($deletedFile);
        $this->client->submit($crawler->selectButton('Cancella commessa e tutti i dati collegati')->form());
        self::assertResponseRedirects('/commesse');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Project::class, $id));
        foreach ([Project::class, Activity::class, TimeEntry::class, Attachment::class, Client::class] as $entityClass) {
            self::assertSame(1, $this->entityManager->getRepository($entityClass)->count([]), $entityClass);
        }
        self::assertSame(0, $this->entityManager->getRepository(Expense::class)->count([]));
        self::assertSame(0, $this->entityManager->getRepository(Payment::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(User::class)->count([]));
        self::assertFileDoesNotExist($deletedFile);
        self::assertFileDoesNotExist($generalFile);
        self::assertFileExists($preservedFile);
        self::assertSame('Documento di test', file_get_contents($preservedFile));
        self::assertNotNull($this->entityManager->getRepository(AuditLog::class)->findOneBy(['action' => AuditAction::ProjectCreated]));
        $audit = $this->entityManager->getRepository(AuditLog::class)->findOneBy(['action' => AuditAction::ProjectDeleted]);
        self::assertInstanceOf(AuditLog::class, $audit);
        self::assertSame($id, $audit->getSubjectId());
        self::assertSame($code, $audit->getDetails()['code']);
        self::assertSame(2, $audit->getDetails()['entry_count']);
        self::assertSame(1, $audit->getDetails()['running_count']);
        self::assertSame(2, $audit->getDetails()['expense_count']);
        self::assertSame(1, $audit->getDetails()['payment_count']);
        self::assertSame(2, $audit->getDetails()['attachment_count']);
        self::assertSame($partner->getUsername(), $audit->getActorIdentifier());
        $this->client->request('GET', '/commesse/'.$id.'/elimina');
        self::assertResponseStatusCodeSame(404);
    }

    public function testEmptyProjectCanBeDeletedAndCancelDoesNotDeleteIt(): void
    {
        $partner = $this->createUser('socio-vuota', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner);
        $id = $project->getId();
        $this->client->loginUser($partner);
        $this->client->request('GET', '/commesse/nuova');
        self::assertSelectorNotExists('a[href$="/elimina"]');
        $crawler = $this->client->request('GET', '/commesse/'.$id.'/elimina');
        $this->client->click($crawler->selectLink('Annulla')->link());
        self::assertResponseIsSuccessful();
        self::assertNotNull($this->entityManager->find(Project::class, $id));
        $crawler = $this->client->request('GET', '/commesse/'.$id.'/elimina');
        $this->client->submit($crawler->selectButton('Cancella commessa e tutti i dati collegati')->form());
        self::assertResponseRedirects('/commesse');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(Project::class, $id));
    }

    public function testResponsibleCollaboratorCannotSeeOrCallDeletion(): void
    {
        $owner = $this->createUser('responsabile-cancellazione');
        $project = $this->createProject($this->createCustomer(), $owner);
        $this->client->loginUser($owner);
        $this->client->request('GET', '/commesse/'.$project->getId().'/modifica');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href$="/elimina"]');
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/commesse/'.$project->getId().'/elimina');
            self::assertResponseStatusCodeSame(403);
        }
        self::assertSame(1, $this->entityManager->getRepository(Project::class)->count([]));
    }

    public function testCsrfIsRequiredAndBoundToTheSpecificProject(): void
    {
        $partner = $this->createUser('socio-csrf-commessa', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner);
        $other = $this->createProject($project->getClient(), $partner);
        $this->client->loginUser($partner);
        $path = '/commesse/'.$project->getId().'/elimina';
        $crawler = $this->client->request('GET', $path);
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        foreach ([[], ['_token' => 'invalid']] as $parameters) {
            $this->client->request('POST', $path, $parameters);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/commesse/'.$other->getId().'/elimina', ['_token' => $token]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(2, $this->entityManager->getRepository(Project::class)->count([]));
    }

    public function testArchivedProjectsCannotBeDeleted(): void
    {
        $partner = $this->createUser('socio-archivio-commessa', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner, status: ProjectStatus::Completed);
        $this->client->loginUser($partner);
        $crawler = $this->client->request('GET', '/commesse/'.$project->getId().'/elimina');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $project->archive();
        $this->entityManager->flush();
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/commesse/'.$project->getId().'/elimina', ['_token' => $token]);
            self::assertResponseStatusCodeSame(403);
        }
        self::assertSame(1, $this->entityManager->getRepository(Project::class)->count([]));
    }

    #[DataProvider('transactionFailures')]
    public function testFailedTransactionRollsBackAllRowsAndRestoresAllDocumentFiles(string $failure): void
    {
        $partner = $this->createUser('socio-rollback-commessa', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner);
        $activity = $this->createTestActivity($project, $partner);
        $this->createTestTimeEntry($activity, $partner, '2026-10-01 09:00', null);
        $first = $this->attachment($project, $partner, $activity);
        $second = $this->attachment($project, $partner);
        $this->entityManager->flush();
        $connection = $this->entityManager->getConnection();
        $trigger = 'audit' === $failure
            ? "CREATE TRIGGER reject_project_audit BEFORE INSERT ON audit_log WHEN NEW.action = 'project.deleted' BEGIN SELECT RAISE(ABORT, 'forced_project_failure'); END"
            : "CREATE TRIGGER reject_project_delete BEFORE DELETE ON project BEGIN SELECT RAISE(ABORT, 'forced_project_failure'); END";
        $connection->executeStatement($trigger);
        try {
            self::getContainer()->get(ProjectDeletionService::class)->delete($project, $partner);
            self::fail('L’errore audit deve annullare la cancellazione.');
        } catch (Exception $exception) {
            self::assertStringContainsString('forced_project_failure', $exception->getMessage());
        }
        foreach (['project' => 1, 'activity' => 1, 'time_entry' => 1, 'attachment' => 2, 'audit_log' => 0] as $table => $count) {
            self::assertSame($count, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$table), $table);
        }
        self::assertSame('Documento di test', file_get_contents($first));
        self::assertSame('Documento di test', file_get_contents($second));
    }

    /** @return iterable<string, array{string}> */
    public static function transactionFailures(): iterable
    {
        yield 'audit' => ['audit'];
        yield 'cancellazione' => ['delete'];
    }

    public function testServiceRechecksArchiveStatusBeforeDeleting(): void
    {
        $partner = $this->createUser('socio-archivio-rivalida', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner, status: ProjectStatus::Completed);
        $this->entityManager->getConnection()->executeStatement('UPDATE project SET archived_at = CURRENT_TIMESTAMP WHERE id = ?', [$project->getId()]);
        $this->expectException(\DomainException::class);
        self::getContainer()->get(ProjectDeletionService::class)->delete($project, $partner);
    }

    #[DataProvider('unauthorizedActors')]
    public function testServiceAlsoRejectsUnauthorizedActors(UserRole $role, bool $active): void
    {
        $actor = $this->createUser('attore-negato', $role, $active);
        $project = $this->createProject($this->createCustomer(), $actor);
        $this->expectException(AccessDeniedException::class);
        self::getContainer()->get(ProjectDeletionService::class)->delete($project, $actor);
    }

    /** @return iterable<string, array{UserRole, bool}> */
    public static function unauthorizedActors(): iterable
    {
        yield 'collaboratore' => [UserRole::Collaborator, true];
        yield 'socio disattivato' => [UserRole::Partner, false];
    }

    private function attachment(Project $project, User $user, ?Activity $activity = null): string
    {
        $directory = self::getContainer()->getParameter('app.attachment_storage_dir');
        self::assertIsString($directory);
        $key = '2026/10/'.bin2hex(random_bytes(16)).'.txt';
        $path = $directory.'/'.$key;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, 'Documento di test');
        $this->fixtureFiles[] = $path;
        $this->entityManager->persist(new Attachment($project, $activity, $user, AttachmentClassification::Technical, 'documento.txt', $key, 'text/plain', 17, hash('sha256', 'Documento di test')));

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->fixtureFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        parent::tearDown();
    }
}
