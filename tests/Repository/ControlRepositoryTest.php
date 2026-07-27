<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Expense;
use App\Entity\Payment;
use App\Enum\ActivityStatus;
use App\Query\CollaboratorEvaluationCriteria;
use App\Repository\ControlRepository;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class ControlRepositoryTest extends DatabaseWebTestCase
{
    public function testProjectMetricsDoNotDuplicateEstimatesAcrossTimeEntries(): void
    {
        $user = $this->createUser('controllo-repository');
        $client = $this->createCustomer('Cliente controllo repository');
        $project = $this->createProject($client, $user, 'Commessa aggregata');
        $project->setEstimatedAmountCents(100_000);

        $first = $this->createTestActivity($project, $user, 'Prima attività');
        $first->setInitialEstimatedMinutes(300)->setRemainingEstimatedMinutes(120)->setStatus(ActivityStatus::InProgress);
        $second = $this->createTestActivity($project, $user, 'Seconda attività');
        $second->setInitialEstimatedMinutes(180)->setRemainingEstimatedMinutes(0)->setStatus(ActivityStatus::Completed);
        $this->entityManager->flush();

        $this->createTestTimeEntry($first, $user, '2026-07-01 08:00:00', '2026-07-01 10:00:00');
        $this->createTestTimeEntry($first, $user, '2026-07-02 08:00:00', '2026-07-02 09:30:00');
        $this->createTestTimeEntry($second, $user, '2026-07-03 08:00:00', '2026-07-03 09:00:00');

        $expense = (new Expense())
            ->setProject($project)
            ->setRecordedBy($user)
            ->setSpentOn(new DateTimeImmutable('2026-07-04'))
            ->setCategory('Materiali')
            ->setDescription('Materiale di prova')
            ->setAmountCents(5_000);
        $payment = (new Payment())
            ->setProject($project)
            ->setRecordedBy($user)
            ->setPaidOn(new DateTimeImmutable('2026-07-05'))
            ->setAmountCents(40_000)
            ->setMethod('Bonifico');
        $this->entityManager->persist($expense);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        /** @var ControlRepository $repository */
        $repository = self::getContainer()->get(ControlRepository::class);
        $rows = $repository->findProjectMetrics(projectId: $project->getId());

        self::assertCount(1, $rows);
        self::assertSame(2, (int) $rows[0]['activity_count']);
        self::assertSame(1, (int) $rows[0]['open_activity_count']);
        self::assertSame(480, (int) $rows[0]['estimated_minutes']);
        self::assertSame(120, (int) $rows[0]['remaining_minutes']);
        self::assertSame(270, (int) $rows[0]['actual_minutes']);
        self::assertSame(22_500, (int) $rows[0]['labour_cost_cents']);
        self::assertSame(5_000, (int) $rows[0]['expense_cents']);
        self::assertSame(40_000, (int) $rows[0]['payment_cents']);
    }

    public function testPeriodCollaboratorAndClientMetricsRespectTheSelectedDates(): void
    {
        $worker = $this->createUser('periodo-controllo');
        $client = $this->createCustomer('Cliente periodo controllo');
        $project = $this->createProject($client, $worker, 'Commessa periodo');
        $activity = $this->createTestActivity($project, $worker, 'Attività periodo');
        $activity->setRemainingEstimatedMinutes(600)->setStatus(ActivityStatus::InProgress);
        $this->entityManager->flush();

        $this->createTestTimeEntry($activity, $worker, '2026-06-15 08:00:00', '2026-06-15 10:00:00', billable: true);
        $this->createTestTimeEntry($activity, $worker, '2026-07-15 08:00:00', '2026-07-15 11:00:00', billable: false);

        /** @var ControlRepository $repository */
        $repository = self::getContainer()->get(ControlRepository::class);
        $from = new DateTimeImmutable('2026-07-01');
        $before = new DateTimeImmutable('2026-08-01');

        $collaborators = $repository->findCollaboratorMetrics($from, $before, $client->getId(), null, new DateTimeImmutable('2026-07-20'));
        $workerRow = array_values(array_filter($collaborators, static fn (array $row): bool => (int) $row['user_id'] === $worker->getId()))[0] ?? null;
        self::assertIsArray($workerRow);
        self::assertSame(180, (int) $workerRow['worked_minutes']);
        self::assertSame(0, (int) $workerRow['billable_minutes']);
        self::assertSame(1, (int) $workerRow['open_activities']);
        self::assertSame(600, (int) $workerRow['remaining_minutes']);

        $clients = $repository->findClientPeriodMetrics($from, $before, $client->getId());
        self::assertCount(1, $clients);
        self::assertSame(180, (int) $clients[0]['worked_minutes']);

        $periods = $repository->findPeriodMetrics($from, $before, $client->getId());
        self::assertCount(1, $periods);
        self::assertSame('2026-07', $periods[0]['month_key']);
        self::assertSame(180, (int) $periods[0]['worked_minutes']);
    }

    public function testCollaboratorWorkEntriesRespectPersonPeriodAndFilters(): void
    {
        $worker = $this->createUser('dettaglio-repository');
        $otherWorker = $this->createUser('dettaglio-altro');
        $responsible = $this->createUser('dettaglio-responsabile');
        $firstClient = $this->createCustomer('Cliente dettaglio A');
        $secondClient = $this->createCustomer('Cliente dettaglio B');
        $firstProject = $this->createProject($firstClient, $responsible, 'Commessa dettaglio A');
        $secondProject = $this->createProject($secondClient, $responsible, 'Commessa dettaglio B');
        $firstActivity = $this->createTestActivity($firstProject, $otherWorker, 'Attività dettaglio A');
        $secondActivity = $this->createTestActivity($secondProject, $otherWorker, 'Attività dettaglio B');

        $this->createTestTimeEntry($firstActivity, $worker, '2026-07-08 08:00:00', '2026-07-08 10:00:00', 'Voce inclusa', true);
        $this->createTestTimeEntry($firstActivity, $worker, '2026-07-09 08:00:00', '2026-07-09 09:00:00', 'Voce non fatturabile', false);
        $this->createTestTimeEntry($secondActivity, $worker, '2026-07-10 08:00:00', '2026-07-10 11:00:00', 'Altro cliente', true);
        $this->createTestTimeEntry($firstActivity, $otherWorker, '2026-07-08 10:00:00', '2026-07-08 11:00:00', 'Altra persona', true);
        $this->createTestTimeEntry($firstActivity, $worker, '2026-06-20 08:00:00', '2026-06-20 09:00:00', 'Fuori periodo', true);

        /** @var ControlRepository $repository */
        $repository = self::getContainer()->get(ControlRepository::class);
        $rows = $repository->findCollaboratorWorkEntries(new CollaboratorEvaluationCriteria(
            userId: $worker->getId() ?? 0,
            periodFrom: new DateTimeImmutable('2026-07-01'),
            periodBefore: new DateTimeImmutable('2026-08-01'),
            clientId: $firstClient->getId(),
            responsibleId: $responsible->getId(),
            projectId: $firstProject->getId(),
            billable: true,
        ));

        self::assertCount(1, $rows);
        self::assertSame('Voce inclusa', $rows[0]['description']);
        self::assertSame(120, (int) $rows[0]['duration_minutes']);
        self::assertGreaterThan(0, (int) $rows[0]['time_entry_id']);
    }
}
