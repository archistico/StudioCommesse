<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\Client;
use App\Entity\Expense;
use App\Entity\Payment;
use App\Entity\Project;
use App\Enum\ActivityPriority;
use App\Enum\ActivityStatus;
use App\Enum\AuditAction;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class EndToEndWorkflowTest extends DatabaseWebTestCase
{
    public function testCompletePaidProjectCanBeReviewedAndArchived(): void
    {
        $partner = $this->createUser('socio-flusso', UserRole::Partner);
        $responsible = $this->createUser('responsabile-flusso');
        $worker = $this->createUser('collaboratore-flusso');
        $project = $this->createProject(
            $this->createCustomer('Cliente flusso completo'),
            $responsible,
            'Commessa flusso completo',
            ProjectStatus::Completed,
        );
        $project->setEstimatedAmountCents(100_000)->setDefaultHourlyRateCents(5_000);
        $activity = $this->createTestActivity($project, $worker, 'Progettazione esecutiva');
        $activity
            ->setStatus(ActivityStatus::Completed)
            ->setPriority(ActivityPriority::High)
            ->setInitialEstimatedMinutes(120);
        $this->createTestTimeEntry(
            $activity,
            $worker,
            '2026-07-20 09:00:00',
            '2026-07-20 11:00:00',
            'Elaborati definitivi',
        );
        $this->entityManager->persist((new Expense())
            ->setProject($project)
            ->setActivity($activity)
            ->setRecordedBy($worker)
            ->setSpentOn(new DateTimeImmutable('2026-07-20'))
            ->setCategory('Stampa')
            ->setDescription('Tavole definitive')
            ->setAmountCents(5_000));
        $this->entityManager->persist((new Payment())
            ->setProject($project)
            ->setRecordedBy($partner)
            ->setPaidOn(new DateTimeImmutable('2026-07-21'))
            ->setAmountCents(100_000)
            ->setDescription('Saldo commessa')
            ->setMethod('Bonifico'));
        $this->entityManager->flush();
        $projectId = (int) $project->getId();
        $this->client->loginUser($partner);

        $this->client->request('GET', '/commesse/'.$projectId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Chiusa operativamente');
        self::assertSelectorTextContains('body', 'Incassata');
        self::assertSelectorTextContains('body', 'Chiusa completamente');
        self::assertSelectorTextContains('body', 'Nessuna criticità rilevata');
        self::assertSelectorTextContains('body', '2:00 / 2:00');

        $crawler = $this->client->request('GET', '/commesse/'.$projectId.'/modifica');
        $this->client->submit($crawler->selectButton('Archivia commessa')->form());

        self::assertResponseRedirects('/commesse/'.$projectId);
        $archivedProject = $this->entityManager->find(Project::class, $projectId);
        self::assertInstanceOf(Project::class, $archivedProject);
        self::assertTrue($archivedProject->isArchived());
        self::assertInstanceOf(AuditLog::class, $this->entityManager->getRepository(AuditLog::class)->findOneBy([
            'action' => AuditAction::ProjectArchived,
            'subjectId' => $projectId,
        ]));

        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Commessa archiviata');
        self::assertSelectorNotExists('a[href="/attivita/commessa/'.$projectId.'/nuova"]');
    }

    public function testCollaboratorCanCreateAssignedWorkAndRecordOwnHoursWithoutFinancialExposure(): void
    {
        $responsible = $this->createUser('responsabile-collaboratore');
        $collaborator = $this->createUser('autore-collaboratore');
        $project = $this->createProject(
            $this->createCustomer('Cliente collaborazione'),
            $responsible,
            'Commessa collaborazione',
            ProjectStatus::InProgress,
        );
        $project->setEstimatedAmountCents(250_000)->setDefaultHourlyRateCents(6_000);
        $this->entityManager->flush();
        $this->client->loginUser($collaborator);

        $crawler = $this->client->request('GET', '/attivita/commessa/'.$project->getId().'/nuova');
        $form = $crawler->selectButton('Crea attività')->form([
            'activity[title]' => 'Rilievo collaboratore',
            'activity[assignee]' => (string) $collaborator->getId(),
            'activity[status]' => ActivityStatus::InProgress->value,
            'activity[priority]' => ActivityPriority::High->value,
            'activity[progressPercent]' => '25',
            'activity[initialEstimatedMinutes]' => '180',
            'activity[remainingEstimatedMinutes]' => '120',
            'activity[startAt]' => '2026-07-22T09:00',
            'activity[dueAt]' => '2026-07-24T18:00',
            'activity[description]' => 'Rilievo e restituzione grafica',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/commesse/'.$project->getId());

        $activity = $this->entityManager->getRepository(\App\Entity\Activity::class)->findOneBy(['title' => 'Rilievo collaboratore']);
        self::assertInstanceOf(\App\Entity\Activity::class, $activity);
        self::assertSame($collaborator->getId(), $activity->getAssignee()?->getId());

        $crawler = $this->client->request('GET', '/ore/attivita/'.$activity->getId().'/nuova');
        $this->client->submit($crawler->selectButton('Salva')->form([
            'time_entry[startedAt]' => '2026-07-22T09:00',
            'time_entry[endedAt]' => '2026-07-22T10:30',
            'time_entry[description]' => 'Rilievo completato',
            'time_entry[billable]' => '1',
        ]));
        self::assertResponseRedirects('/ore/attivita/'.$activity->getId());

        $this->client->request('GET', '/attivita');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Rilievo collaboratore');
        self::assertSelectorTextContains('table', '1:30');

        $this->client->request('GET', '/economia/commessa/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Le mie spese sulla commessa');
        self::assertSelectorTextContains('body', 'riservati ai soci');
        self::assertSelectorNotExists('a[href="/economia/commessa/'.$project->getId().'/incasso"]');
    }

    public function testClosedProjectWithOpenActivityIsReportedAsInconsistent(): void
    {
        $partner = $this->createUser('socio-incoerenza', UserRole::Partner);
        $project = $this->createProject(
            $this->createCustomer('Cliente incoerenza'),
            $partner,
            'Commessa chiusa male',
            ProjectStatus::Completed,
        );
        $project->setEstimatedAmountCents(50_000);
        $this->createTestActivity($project, $partner, 'Attività ancora aperta')
            ->setStatus(ActivityStatus::InProgress)
            ->setRemainingEstimatedMinutes(60);
        $this->entityManager->flush();
        $projectId = (int) $project->getId();
        $this->client->loginUser($partner);

        $this->client->request('GET', '/commesse/'.$projectId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Stato incoerente');
        self::assertSelectorTextContains('body', 'Richiede attenzione');
        self::assertSelectorTextContains('body', 'Stato chiuso con attività o timer ancora aperti');
    }

    public function testArchivedProjectRequiresClientRestorationBeforeProjectRestoration(): void
    {
        $partner = $this->createUser('socio-ripristino', UserRole::Partner);
        $client = $this->createCustomer('Cliente da ripristinare');
        $project = $this->createProject($client, $partner, 'Commessa da ripristinare', ProjectStatus::Completed);
        $project->archive();
        $client->archive();
        $this->entityManager->flush();
        $projectId = (int) $project->getId();
        $clientId = (int) $client->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/commesse/'.$projectId);
        $this->client->submit($crawler->selectButton('Ripristina')->form());
        self::assertResponseRedirects('/commesse/'.$projectId);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Ripristinare prima il cliente');
        $stillArchivedProject = $this->entityManager->find(Project::class, $projectId);
        self::assertInstanceOf(Project::class, $stillArchivedProject);
        self::assertTrue($stillArchivedProject->isArchived());

        $crawler = $this->client->request('GET', '/clienti/'.$clientId);
        $this->client->submit($crawler->selectButton('Ripristina')->form());
        self::assertResponseRedirects('/clienti/'.$clientId);
        $restoredClient = $this->entityManager->find(Client::class, $clientId);
        self::assertInstanceOf(Client::class, $restoredClient);
        self::assertFalse($restoredClient->isArchived());

        $crawler = $this->client->request('GET', '/commesse/'.$projectId);
        $this->client->submit($crawler->selectButton('Ripristina')->form());
        self::assertResponseRedirects('/commesse/'.$projectId);
        $restoredProject = $this->entityManager->find(Project::class, $projectId);
        self::assertInstanceOf(Project::class, $restoredProject);
        self::assertFalse($restoredProject->isArchived());
    }
}
