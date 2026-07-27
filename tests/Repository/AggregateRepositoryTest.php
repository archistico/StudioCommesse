<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Activity;
use App\Entity\Expense;
use App\Entity\Payment;
use App\Entity\TimeEntry;
use App\Repository\ExpenseRepository;
use App\Repository\PaymentRepository;
use App\Repository\TimeEntryRepository;
use App\Service\ProjectFinancialService;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class AggregateRepositoryTest extends DatabaseWebTestCase
{
    public function testBulkDurationAndFinancialAggregatesPreserveTotals(): void
    {
        $user = $this->createUser('aggregati');
        $client = $this->createCustomer('Cliente aggregati');
        $firstProject = $this->createProject($client, $user, 'Prima commessa');
        $secondProject = $this->createProject($client, $user, 'Seconda commessa');
        $firstProject->setEstimatedAmountCents(100_000);
        $secondProject->setEstimatedAmountCents(80_000);

        $firstActivity = $this->createActivity($firstProject, $user, 'Attività A');
        $secondActivity = $this->createActivity($firstProject, $user, 'Attività B');
        $thirdActivity = $this->createActivity($secondProject, $user, 'Attività C');

        $this->entityManager->persist($this->createEntry($firstActivity, $user, '2026-07-27 09:00:00', '2026-07-27 10:05:59', 5_000));
        $this->entityManager->persist($this->createEntry($firstActivity, $user, '2026-07-27 10:10:00', '2026-07-27 10:40:00', 2_500));
        $this->entityManager->persist($this->createEntry($secondActivity, $user, '2026-07-27 11:00:00', '2026-07-27 12:30:00', 7_500));
        $this->entityManager->persist($this->createEntry($thirdActivity, $user, '2026-07-27 13:00:00', '2026-07-27 15:00:00', 10_000));

        $this->entityManager->persist((new Expense())
            ->setProject($firstProject)
            ->setRecordedBy($user)
            ->setDescription('Spesa prima')
            ->setAmountCents(12_000));
        $this->entityManager->persist((new Expense())
            ->setProject($secondProject)
            ->setRecordedBy($user)
            ->setDescription('Spesa seconda')
            ->setAmountCents(3_000));
        $this->entityManager->persist((new Payment())
            ->setProject($firstProject)
            ->setRecordedBy($user)
            ->setDescription('Acconto')
            ->setAmountCents(50_000));
        $this->entityManager->persist((new Payment())
            ->setProject($secondProject)
            ->setRecordedBy($user)
            ->setDescription('Saldo parziale')
            ->setAmountCents(20_000));
        $this->entityManager->flush();

        /** @var TimeEntryRepository $timeEntries */
        $timeEntries = self::getContainer()->get(TimeEntryRepository::class);
        /** @var ExpenseRepository $expenses */
        $expenses = self::getContainer()->get(ExpenseRepository::class);
        /** @var PaymentRepository $payments */
        $payments = self::getContainer()->get(PaymentRepository::class);
        /** @var ProjectFinancialService $financials */
        $financials = self::getContainer()->get(ProjectFinancialService::class);

        $activityMinutes = $timeEntries->sumMinutesByActivityIds([
            self::requireId($firstActivity->getId()),
            self::requireId($secondActivity->getId()),
            self::requireId($thirdActivity->getId()),
        ]);
        self::assertSame(95, $activityMinutes[self::requireId($firstActivity->getId())]);
        self::assertSame(90, $activityMinutes[self::requireId($secondActivity->getId())]);
        self::assertSame(120, $activityMinutes[self::requireId($thirdActivity->getId())]);

        $projectIds = [self::requireId($firstProject->getId()), self::requireId($secondProject->getId())];
        self::assertSame([15_000, 10_000], array_values($timeEntries->sumCostCentsByProjectIds($projectIds)));
        self::assertSame([12_000, 3_000], array_values($expenses->sumCentsByProjectIds($projectIds)));
        self::assertSame([50_000, 20_000], array_values($payments->sumCentsByProjectIds($projectIds)));

        $summaries = $financials->summarizeMany([$firstProject, $secondProject]);
        self::assertCount(2, $summaries);
        self::assertSame(27_000, $summaries[0]->getTotalCostCents());
        self::assertSame(73_000, $summaries[0]->getMarginCents());
        self::assertSame(13_000, $summaries[1]->getTotalCostCents());
        self::assertSame(67_000, $summaries[1]->getMarginCents());
    }

    private function createActivity(\App\Entity\Project $project, \App\Entity\User $user, string $title): Activity
    {
        $activity = (new Activity())
            ->setProject($project)
            ->setAssignee($user)
            ->setCreatedBy($user)
            ->setTitle($title);
        $this->entityManager->persist($activity);

        return $activity;
    }

    private function createEntry(
        Activity $activity,
        \App\Entity\User $user,
        string $startedAt,
        string $endedAt,
        int $costSnapshotCents,
    ): TimeEntry {
        $entry = (new TimeEntry())
            ->setActivity($activity)
            ->setUser($user)
            ->setStartedAt(new DateTimeImmutable($startedAt))
            ->setEndedAt(new DateTimeImmutable($endedAt));

        $duration = $entry->getDurationMinutes();
        $hourlyRate = (int) round($costSnapshotCents * 60 / $duration);

        return $entry->applyRateSnapshot($hourlyRate);
    }

    private static function requireId(?int $id): int
    {
        if (null === $id) {
            throw new \LogicException('Identificatore Doctrine non assegnato.');
        }

        return $id;
    }
}
