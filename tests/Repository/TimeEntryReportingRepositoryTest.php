<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Query\TimeEntrySearchCriteria;
use App\Repository\TimeEntryRepository;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class TimeEntryReportingRepositoryTest extends DatabaseWebTestCase
{
    public function testReportPaginationAndSummaryUseTheWholeFilteredResult(): void
    {
        $worker = $this->createUser('paginazione');
        $project = $this->createProject($this->createCustomer('Cliente Paginazione'), $worker);
        $activity = $this->createTestActivity($project, $worker, 'Attività con molte registrazioni');

        for ($index = 0; $index < 55; ++$index) {
            $start = (new DateTimeImmutable('2026-06-01 08:00:00'))->modify(sprintf('+%d days', $index));
            $this->createTestTimeEntry(
                $activity,
                $worker,
                $start->format('Y-m-d H:i:s'),
                $start->modify('+30 minutes')->format('Y-m-d H:i:s'),
                sprintf('Registrazione %02d', $index + 1),
                0 === $index % 2,
            );
        }

        /** @var TimeEntryRepository $repository */
        $repository = self::getContainer()->get(TimeEntryRepository::class);
        $criteria = new TimeEntrySearchCriteria(
            projectId: $project->getId(),
            userId: $worker->getId(),
            page: 2,
            perPage: 50,
        );

        $page = $repository->findPage($criteria);
        $summary = $repository->summarize($criteria);

        self::assertSame(55, $page->totalItems);
        self::assertSame(2, $page->totalPages);
        self::assertSame(2, $page->page);
        self::assertCount(5, $page->items);
        self::assertSame(51, $page->getFirstItemNumber());
        self::assertSame(55, $page->getLastItemNumber());
        self::assertSame(55, $summary->entryCount);
        self::assertSame(1, $summary->userCount);
        self::assertSame(1, $summary->projectCount);
        self::assertSame(1_650, $summary->totalMinutes);
    }

    public function testContributorBreakdownIsOrderedByDurationAndExcludesRunningTimers(): void
    {
        $assignee = $this->createUser('assegnato');
        $first = $this->createUser('prima');
        $second = $this->createUser('seconda');
        $project = $this->createProject($this->createCustomer('Cliente Breakdown'), $assignee);
        $activity = $this->createTestActivity($project, $assignee, 'Attività Breakdown');
        $this->createTestTimeEntry($activity, $first, '2026-07-01 08:00:00', '2026-07-01 11:00:00');
        $this->createTestTimeEntry($activity, $second, '2026-07-01 12:00:00', '2026-07-01 13:00:00');
        $this->createTestTimeEntry($activity, $assignee, '2026-07-01 14:00:00', null);

        /** @var TimeEntryRepository $repository */
        $repository = self::getContainer()->get(TimeEntryRepository::class);
        $activityId = $activity->getId();
        self::assertNotNull($activityId);

        $summary = $repository->summarizeMinutesByActivityAndUserIds([$activityId]);

        self::assertSame(240, $summary[$activityId]['total_minutes']);
        self::assertCount(2, $summary[$activityId]['contributors']);
        self::assertSame('Prima', $summary[$activityId]['contributors'][0]['display_name']);
        self::assertSame(180, $summary[$activityId]['contributors'][0]['total_minutes']);
        self::assertSame('Seconda', $summary[$activityId]['contributors'][1]['display_name']);
        self::assertSame(60, $summary[$activityId]['contributors'][1]['total_minutes']);
    }
}
