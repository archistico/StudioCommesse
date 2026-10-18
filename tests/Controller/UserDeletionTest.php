<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Attachment;
use App\Entity\AuditLog;
use App\Entity\Expense;
use App\Entity\Payment;
use App\Entity\User;
use App\Enum\AttachmentClassification;
use App\Enum\AuditAction;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class UserDeletionTest extends DatabaseWebTestCase
{
    #[DataProvider('unusedUsers')]
    public function testPartnerCanDeleteAnUnusedUserAfterConfirmationAndAuditIsPreserved(UserRole $role, bool $active): void
    {
        $partner = $this->createUser('socio-elimina', UserRole::Partner);
        $target = $this->createUser('utente-inutilizzato', $role, $active);
        $id = $target->getId();
        $this->entityManager->persist(new AuditLog(AuditAction::UserCreated, $partner->getUsername(), User::class, $id));
        $this->entityManager->flush();
        $this->client->loginUser($partner);
        $this->client->request('GET', '/admin/utenti/'.$id.'/modifica');
        self::assertSelectorExists('a[href="/admin/utenti/'.$id.'/elimina"]');
        $crawler = $this->client->request('GET', '/admin/utenti/'.$id.'/elimina');
        self::assertResponseIsSuccessful();
        self::assertNotNull($this->entityManager->find(User::class, $id));
        $this->client->submit($crawler->selectButton('Elimina utente')->form());
        self::assertResponseRedirects('/admin/utenti');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(User::class, $id));
        self::assertNotNull($this->entityManager->getRepository(AuditLog::class)->findOneBy(['action' => AuditAction::UserCreated]));
        $audit = $this->entityManager->getRepository(AuditLog::class)->findOneBy(['action' => AuditAction::UserDeleted]);
        self::assertInstanceOf(AuditLog::class, $audit);
        self::assertSame($id, $audit->getSubjectId());
        self::assertSame('utente-inutilizzato', $audit->getDetails()['username']);
    }

    /** @return iterable<string, array{UserRole, bool}> */
    public static function unusedUsers(): iterable
    {
        yield 'collaboratore attivo' => [UserRole::Collaborator, true];
        yield 'collaboratore disattivato' => [UserRole::Collaborator, false];
        yield 'altro socio attivo' => [UserRole::Partner, true];
        yield 'socio disattivato' => [UserRole::Partner, false];
    }

    #[DataProvider('linkedRecords')]
    public function testAnyLinkedBusinessRecordPreventsDeletion(string $kind): void
    {
        $partner = $this->createUser('socio-collegamenti', UserRole::Partner);
        $target = $this->createUser('utente-collegato');
        $project = $this->createProject($this->createCustomer(), in_array($kind, ['project', 'archived_project'], true) ? $target : $partner);
        if ('archived_project' === $kind) {
            $project->setStatus(ProjectStatus::Completed);
            $project->archive();
        }
        $activity = $this->createTestActivity($project, 'assignee' === $kind ? $target : $partner);
        if ('creator' === $kind) {
            $activity->setCreatedBy($target);
        } elseif ('hours' === $kind || 'timer' === $kind) {
            $this->createTestTimeEntry($activity, $target, '2026-10-01 09:00', 'timer' === $kind ? null : '2026-10-01 10:00');
        } elseif ('expense' === $kind) {
            $this->entityManager->persist((new Expense())->setProject($project)->setRecordedBy($target)->setDescription('Spesa')->setAmountCents(100));
        } elseif ('payment' === $kind) {
            $this->entityManager->persist((new Payment())->setProject($project)->setRecordedBy($target)->setAmountCents(100));
        } elseif ('attachment' === $kind) {
            $this->entityManager->persist(new Attachment($project, null, $target, AttachmentClassification::Technical, 'test.pdf', '2026/10/'.str_repeat('a', 32).'.pdf', 'application/pdf', 1, str_repeat('a', 64), 'Documento'));
        }
        $this->entityManager->flush();
        $id = $target->getId();
        $this->client->loginUser($partner);
        $crawler = $this->client->request('GET', '/admin/utenti/'.$id.'/elimina');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-warning', 'dati collegati');
        self::assertSelectorNotExists('button.btn-danger');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/utenti/'.$id.'/elimina', ['_token' => $token]);
        self::assertResponseStatusCodeSame(409);
        self::assertNotNull($this->entityManager->find(User::class, $id));
    }

    /** @return iterable<string, array{string}> */
    public static function linkedRecords(): iterable
    {
        foreach (['project', 'archived_project', 'assignee', 'creator', 'hours', 'timer', 'expense', 'payment', 'attachment'] as $kind) {
            yield $kind => [$kind];
        }
    }

    public function testDeletionRechecksLinksAddedAfterTheConfirmationPage(): void
    {
        $partner = $this->createUser('socio-rivalida', UserRole::Partner);
        $target = $this->createUser('utente-rivalida');
        $this->client->loginUser($partner);
        $crawler = $this->client->request('GET', '/admin/utenti/'.$target->getId().'/elimina');
        $form = $crawler->selectButton('Elimina utente')->form();
        $this->createProject($this->createCustomer(), $target);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(409);
        self::assertNotNull($this->entityManager->find(User::class, $target->getId()));
    }

    public function testSelfDeletionAndRequestsWithoutCsrfAreRejected(): void
    {
        $partner = $this->createUser('socio-stesso', UserRole::Partner);
        $this->client->loginUser($partner);
        $path = '/admin/utenti/'.$partner->getId().'/elimina';
        $crawler = $this->client->request('GET', $path);
        self::assertSelectorTextContains('.alert-warning', 'stesso account');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', $path, ['_token' => $token]);
        self::assertResponseStatusCodeSame(409);
        $this->client->request('POST', $path);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->entityManager->getRepository(User::class)->count([]));
    }

    public function testCollaboratorsCannotDeleteUsers(): void
    {
        $user = $this->createUser('collaboratore-elimina');
        $this->client->loginUser($user);
        foreach (['GET', 'POST'] as $method) {
            $this->client->request($method, '/admin/utenti/'.$user->getId().'/elimina');
            self::assertResponseStatusCodeSame(403);
        }
    }
}
