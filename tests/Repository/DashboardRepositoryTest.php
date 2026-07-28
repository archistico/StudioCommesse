<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Enum\ActivityStatus;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Repository\DashboardRepository;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class DashboardRepositoryTest extends DatabaseWebTestCase
{
    public function testSummaryConsolidatesDashboardCountersInOneRepositoryCall(): void
    {
        $partner = $this->createUser('dashboard-partner', UserRole::Partner);
        $collaborator = $this->createUser('dashboard-collaborator');
        $this->createUser('dashboard-inactive', active: false);
        $client = $this->createCustomer('Cliente dashboard performance');

        $waiting = $this->createProject($client, $partner, 'Commessa in attesa', ProjectStatus::Waiting)
            ->setDueDate(new DateTimeImmutable('2026-07-01'));
        $completed = $this->createProject($client, $partner, 'Commessa completata', ProjectStatus::Completed)
            ->setDueDate(new DateTimeImmutable('2026-06-01'));
        $this->entityManager->flush();

        $openActivity = $this->createTestActivity($waiting, $collaborator, 'Attività aperta')
            ->setStatus(ActivityStatus::InProgress)
            ->setDueAt(new DateTimeImmutable('2026-07-10 10:00:00'));
        $this->createTestActivity($completed, $collaborator, 'Attività chiusa')
            ->setStatus(ActivityStatus::Completed)
            ->setDueAt(new DateTimeImmutable('2026-06-01 10:00:00'));
        $this->entityManager->flush();

        $this->createTestTimeEntry($openActivity, $collaborator, '2026-07-05 09:00:00', '2026-07-05 10:30:00');
        $this->createTestTimeEntry($openActivity, $collaborator, '2026-06-30 09:00:00', '2026-06-30 11:00:00');
        $this->createTestTimeEntry($openActivity, $collaborator, '2026-07-06 09:00:00', null);

        /** @var DashboardRepository $repository */
        $repository = self::getContainer()->get(DashboardRepository::class);
        $summary = $repository->summarize(
            new DateTimeImmutable('2026-07-01 00:00:00'),
            new DateTimeImmutable('2026-08-01 00:00:00'),
            new DateTimeImmutable('2026-07-28 12:00:00'),
        );

        self::assertSame(1, $summary->openProjects);
        self::assertSame(1, $summary->waitingProjects);
        self::assertSame(1, $summary->overdueProjects);
        self::assertSame(1, $summary->activeClients);
        self::assertSame(1, $summary->openActivities);
        self::assertSame(1, $summary->overdueActivities);
        self::assertSame(90, $summary->workedMinutes);
        self::assertSame(2, $summary->activeUsers);
        self::assertSame(1, $summary->activePartners);
        self::assertSame(1, $summary->activeCollaborators);
    }
}
