<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Payment;
use App\Enum\ActivityStatus;
use App\Enum\EconomicClosureStatus;
use App\Enum\OperationalClosureStatus;
use App\Enum\OverallClosureStatus;
use App\Enum\ProjectStatus;
use App\Query\ControlSearchCriteria;
use App\Service\ProjectControlService;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class ProjectControlServiceTest extends DatabaseWebTestCase
{
    public function testCompletedAndFullyCollectedProjectIsClosedCompletely(): void
    {
        $partner = $this->createUser('chiusura-completa');
        $client = $this->createCustomer('Cliente chiusura completa');
        $project = $this->createProject($client, $partner, 'Commessa chiusa', ProjectStatus::Completed);
        $project->setEstimatedAmountCents(50_000);
        $activity = $this->createTestActivity($project, $partner, 'Attività chiusa');
        $activity->setStatus(ActivityStatus::Completed)->setInitialEstimatedMinutes(120);
        $payment = (new Payment())
            ->setProject($project)
            ->setRecordedBy($partner)
            ->setPaidOn(new DateTimeImmutable('2026-07-01'))
            ->setAmountCents(50_000)
            ->setMethod('Bonifico');
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        /** @var ProjectControlService $service */
        $service = self::getContainer()->get(ProjectControlService::class);
        $row = $service->analyze($project, new DateTimeImmutable('2026-07-20'));

        self::assertNotNull($row);
        self::assertSame(OperationalClosureStatus::Closed, $row->operationalStatus);
        self::assertSame(EconomicClosureStatus::Closed, $row->economicStatus);
        self::assertSame(OverallClosureStatus::Closed, $row->overallStatus);
    }

    public function testClosedProjectWithOpenActivityIsMarkedInconsistent(): void
    {
        $user = $this->createUser('chiusura-incoerente');
        $project = $this->createProject($this->createCustomer('Cliente incoerente'), $user, 'Commessa incoerente', ProjectStatus::Completed);
        $project->setEstimatedAmountCents(10_000);
        $this->createTestActivity($project, $user, 'Attività ancora aperta')->setStatus(ActivityStatus::InProgress);
        $this->entityManager->flush();

        /** @var ProjectControlService $service */
        $service = self::getContainer()->get(ProjectControlService::class);
        $row = $service->analyze($project, new DateTimeImmutable('2026-07-20'));

        self::assertNotNull($row);
        self::assertSame(OperationalClosureStatus::Inconsistent, $row->operationalStatus);
        self::assertSame(OverallClosureStatus::Attention, $row->overallStatus);
        self::assertContains('Stato chiuso con attività o timer ancora aperti', $row->alerts);
    }

    public function testStalledProjectAndOverloadedCollaboratorAreDetected(): void
    {
        $worker = $this->createUser('sovraccarico');
        $project = $this->createProject($this->createCustomer('Cliente carico'), $worker, 'Commessa ferma', ProjectStatus::InProgress);
        $project->setEstimatedAmountCents(20_000);
        for ($index = 0; $index < 9; ++$index) {
            $this->createTestActivity($project, $worker, 'Attività '.$index)
                ->setStatus(ActivityStatus::InProgress)
                ->setRemainingEstimatedMinutes(300);
        }
        $this->entityManager->flush();
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement("UPDATE project SET updated_at = '2026-06-01 08:00:00' WHERE id = :id", ['id' => $project->getId()]);
        $connection->executeStatement("UPDATE activity SET updated_at = '2026-06-01 08:00:00' WHERE project_id = :id", ['id' => $project->getId()]);

        /** @var ProjectControlService $service */
        $service = self::getContainer()->get(ProjectControlService::class);
        $criteria = new ControlSearchCriteria(
            periodFrom: new DateTimeImmutable('2026-07-01'),
            periodBefore: new DateTimeImmutable('2026-08-01'),
        );
        $dashboard = $service->build($criteria, new DateTimeImmutable('2026-07-20 12:00:00'));
        $projectRow = array_values(array_filter($dashboard->projects, static fn ($row): bool => $row->projectId === $project->getId()))[0] ?? null;
        $workerRow = array_values(array_filter($dashboard->collaborators, static fn ($row): bool => $row->userId === $worker->getId()))[0] ?? null;

        self::assertNotNull($projectRow);
        self::assertTrue($projectRow->stalled);
        self::assertContains('Nessun avanzamento da oltre 14 giorni', $projectRow->alerts);
        self::assertNotNull($workerRow);
        self::assertTrue($workerRow->overloaded);
        self::assertSame(9, $workerRow->openActivities);
        self::assertSame(2_700, $workerRow->remainingMinutes);
    }
}
