<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\Project;
use App\Enum\AuditAction;
use App\Enum\ProjectPriority;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;

final class ProjectManagementTest extends DatabaseWebTestCase
{
    public function testPartnerCreatesProjectWithAutomaticCode(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $responsible = $this->createUser('responsabile');
        $client = $this->createCustomer('Cliente Alfa');
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/commesse/nuova');
        $form = $crawler->selectButton('Crea commessa')->form([
            'project[name]' => 'Ristrutturazione sede',
            'project[client]' => (string) $client->getId(),
            'project[responsible]' => (string) $responsible->getId(),
            'project[status]' => ProjectStatus::NotStarted->value,
            'project[priority]' => ProjectPriority::High->value,
            'project[startDate]' => '',
            'project[dueDate]' => '2026-12-31',
            'project[description]' => 'Descrizione operativa',
            'project[waitingReason]' => '',
            'project[privateNote]' => 'Nota riservata',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $created = $this->entityManager->getRepository(Project::class)->findOneBy(['name' => 'Ristrutturazione sede']);
        self::assertInstanceOf(Project::class, $created);
        self::assertMatchesRegularExpression('/^\d{4}-001$/', $created->getCode());
        self::assertSame(ProjectPriority::High, $created->getPriority());
        self::assertSame($responsible->getId(), $created->getResponsible()?->getId());

        $audit = $this->entityManager->getRepository(AuditLog::class)->findOneBy([
            'action' => AuditAction::ProjectCreated,
            'subjectId' => $created->getId(),
        ]);
        self::assertInstanceOf(AuditLog::class, $audit);
    }

    public function testEveryCollaboratorCanReadEveryProjectButPrivateNoteIsProtected(): void
    {
        $responsible = $this->createUser('responsabile');
        $viewer = $this->createUser('osservatore');
        $client = $this->createCustomer();
        $project = $this->createProject($client, $responsible);
        $project->setPrivateNote('SEGRETO-COMMESSA');
        $this->entityManager->flush();

        $this->client->loginUser($viewer);
        $this->client->request('GET', '/commesse/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('SEGRETO-COMMESSA', (string) $this->client->getResponse()->getContent());

        $this->client->loginUser($responsible);
        $this->client->request('GET', '/commesse/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'SEGRETO-COMMESSA');
    }

    public function testResponsibleCanEditOwnProjectButCannotReassignIt(): void
    {
        $responsible = $this->createUser('responsabile');
        $client = $this->createCustomer();
        $project = $this->createProject($client, $responsible);
        $this->client->loginUser($responsible);
        $projectId = $project->getId();
        self::assertNotNull($projectId);

        $crawler = $this->client->request('GET', '/commesse/'.$projectId.'/modifica');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('[name="project[responsible]"]'));
        self::assertCount(0, $crawler->filter('[name="project[client]"]'));

        $form = $crawler->selectButton('Salva modifiche')->form([
            'project[name]' => 'Commessa aggiornata',
            'project[status]' => ProjectStatus::InProgress->value,
            'project[priority]' => ProjectPriority::Urgent->value,
            'project[startDate]' => '2026-07-27',
            'project[dueDate]' => '2026-08-31',
            'project[description]' => '',
            'project[waitingReason]' => '',
            'project[privateNote]' => 'Nota del responsabile',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects('/commesse/'.$projectId);
        $project = $this->entityManager->find(Project::class, $projectId);
        self::assertInstanceOf(Project::class, $project);
        self::assertSame('Commessa aggiornata', $project->getName());
        self::assertSame(ProjectPriority::Urgent, $project->getPriority());
        self::assertSame($responsible->getId(), $project->getResponsible()?->getId());
    }

    public function testPartnerSeesFinancialFieldsBeforeProjectSubmitButton(): void
    {
        $partner = $this->createUser('socio-form', UserRole::Partner);
        $project = $this->createProject($this->createCustomer('Cliente Form'), $partner);
        $this->client->loginUser($partner);

        $this->client->request('GET', '/commesse/'.$project->getId().'/modifica');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        $estimatedPosition = strpos($html, 'name="project[estimatedAmountCents]"');
        $ratePosition = strpos($html, 'name="project[defaultHourlyRateCents]"');
        $submitPosition = strpos($html, '>Salva modifiche</button>');
        self::assertIsInt($estimatedPosition);
        self::assertIsInt($ratePosition);
        self::assertIsInt($submitPosition);
        self::assertLessThan($submitPosition, $estimatedPosition);
        self::assertLessThan($submitPosition, $ratePosition);
    }

    public function testUnrelatedCollaboratorCannotEditProject(): void
    {
        $responsible = $this->createUser('responsabile');
        $other = $this->createUser('altro');
        $project = $this->createProject($this->createCustomer(), $responsible);
        $this->client->loginUser($other);

        $this->client->request('GET', '/commesse/'.$project->getId().'/modifica');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPartnerCanArchiveOnlyClosedProject(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $client = $this->createCustomer();
        $openProject = $this->createProject($client, $partner);
        $closedProject = $this->createProject($client, $partner, 'Commessa completata', ProjectStatus::Completed);
        $this->client->loginUser($partner);
        $openProjectId = $openProject->getId();
        $closedProjectId = $closedProject->getId();
        self::assertNotNull($openProjectId);
        self::assertNotNull($closedProjectId);

        $crawler = $this->client->request('GET', '/commesse/'.$openProjectId.'/modifica');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->selectButton('Archivia commessa'), 'Il socio deve vedere il comando Archivia nella modifica della commessa aperta.');
        $this->client->submit($crawler->selectButton('Archivia commessa')->form());
        $openProject = $this->entityManager->find(Project::class, $openProjectId);
        self::assertInstanceOf(Project::class, $openProject);
        self::assertFalse($openProject->isArchived());

        $crawler = $this->client->request('GET', '/commesse/'.$closedProjectId.'/modifica');
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->selectButton('Archivia commessa'), 'Il socio deve vedere il comando Archivia nella modifica della commessa completata.');
        $this->client->submit($crawler->selectButton('Archivia commessa')->form());
        $closedProject = $this->entityManager->find(Project::class, $closedProjectId);
        self::assertInstanceOf(Project::class, $closedProject);
        self::assertTrue($closedProject->isArchived());
    }

    public function testProjectFiltersByStatusAndSearchText(): void
    {
        $viewer = $this->createUser('viewer');
        $client = $this->createCustomer('Cliente Beta');
        $this->createProject($client, $viewer, 'Progetto attivo', ProjectStatus::InProgress);
        $this->createProject($client, $viewer, 'Progetto fermo', ProjectStatus::Waiting);
        $this->client->loginUser($viewer);

        $this->client->request('GET', '/commesse?stato=waiting&q=fermo');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Progetto fermo');
        self::assertStringNotContainsString('Progetto attivo', (string) $this->client->getResponse()->getContent());
    }

    public function testArchivedProjectIsReadOnlyAlsoForPartner(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $project = $this->createProject(
            $this->createCustomer(),
            $partner,
            'Commessa archiviata',
            ProjectStatus::Completed,
        );
        $project->archive();
        $this->entityManager->flush();
        $this->client->loginUser($partner);

        $this->client->request('GET', '/commesse/'.$project->getId().'/modifica');

        self::assertResponseStatusCodeSame(403);
    }

    public function testCollaboratorCannotCreateProject(): void
    {
        $collaborator = $this->createUser('collaboratore');
        $this->client->loginUser($collaborator);

        $this->client->request('GET', '/commesse/nuova');

        self::assertResponseStatusCodeSame(403);
    }
}
