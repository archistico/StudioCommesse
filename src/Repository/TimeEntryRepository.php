<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Query\TimeEntryPage;
use App\Query\TimeEntrySearchCriteria;
use App\Query\TimeEntrySummary;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TimeEntry> */
final class TimeEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeEntry::class);
    }

    public function save(TimeEntry $entry, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entry);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(TimeEntry $entry, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entry);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findRunningForUser(User $user): ?TimeEntry
    {
        return $this->findOneBy(['user' => $user, 'endedAt' => null], ['startedAt' => 'DESC']);
    }

    /** @return list<TimeEntry> */
    public function findForActivity(Activity $activity): array
    {
        $result = $this->createQueryBuilder('entry')
            ->addSelect('user')
            ->join('entry.user', 'user')
            ->andWhere('entry.activity = :activity')
            ->setParameter('activity', $activity)
            ->orderBy('entry.startedAt', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var list<TimeEntry> $result */
        return $result;
    }

    public function findPage(TimeEntrySearchCriteria $criteria): TimeEntryPage
    {
        $countBuilder = $this->createFilteredQueryBuilder($criteria)
            ->select('COUNT(entry.id)');
        $totalItems = (int) $countBuilder->getQuery()->getSingleScalarResult();
        $perPage = max(1, min(200, $criteria->perPage));
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = max(1, min($criteria->page, $totalPages));

        $result = $this->createFilteredQueryBuilder($criteria)
            ->select('entry', 'user', 'activity', 'assignee', 'project', 'client')
            ->join('project.client', 'client')
            ->orderBy('entry.startedAt', 'DESC')
            ->addOrderBy('entry.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        /** @var list<TimeEntry> $result */
        return new TimeEntryPage($result, $totalItems, $page, $perPage, $totalPages);
    }

    public function summarize(TimeEntrySearchCriteria $criteria): TimeEntrySummary
    {
        $conditions = [];
        $parameters = [];

        if (null !== $criteria->projectId) {
            $conditions[] = 'activity.project_id = :project_id';
            $parameters['project_id'] = $criteria->projectId;
        }
        if (null !== $criteria->activityId) {
            $conditions[] = 'time_entry.activity_id = :activity_id';
            $parameters['activity_id'] = $criteria->activityId;
        }
        if (null !== $criteria->userId) {
            $conditions[] = 'time_entry.user_id = :user_id';
            $parameters['user_id'] = $criteria->userId;
        }
        if (null !== $criteria->startedFrom) {
            $conditions[] = 'time_entry.started_at >= :started_from';
            $parameters['started_from'] = $criteria->startedFrom->format('Y-m-d H:i:s');
        }
        if (null !== $criteria->startedBefore) {
            $conditions[] = 'time_entry.started_at < :started_before';
            $parameters['started_before'] = $criteria->startedBefore->format('Y-m-d H:i:s');
        }
        if (null !== $criteria->billable) {
            $conditions[] = 'time_entry.billable = :billable';
            $parameters['billable'] = $criteria->billable ? 1 : 0;
        }

        $where = [] === $conditions ? '' : 'WHERE '.implode(' AND ', $conditions);
        $row = $this->getEntityManager()->getConnection()->fetchAssociative(
            <<<SQL
SELECT COUNT(time_entry.id) AS entry_count,
       COUNT(DISTINCT time_entry.user_id) AS user_count,
       COUNT(DISTINCT activity.project_id) AS project_count,
       COALESCE(SUM(
           CASE WHEN time_entry.ended_at IS NULL THEN 0
                ELSE MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER))
           END
       ), 0) AS total_minutes
FROM time_entry
INNER JOIN activity ON activity.id = time_entry.activity_id
{$where}
SQL,
            $parameters,
        );

        if (false === $row) {
            return new TimeEntrySummary(0, 0, 0, 0);
        }

        return new TimeEntrySummary(
            (int) ($row['total_minutes'] ?? 0),
            (int) ($row['entry_count'] ?? 0),
            (int) ($row['user_count'] ?? 0),
            (int) ($row['project_count'] ?? 0),
        );
    }

    public function sumMinutesForActivity(Activity $activity): int
    {
        $id = $activity->getId();
        if (null === $id) {
            return 0;
        }

        return $this->sumMinutesByActivityIds([$id])[$id] ?? 0;
    }

    /**
     * Esegue una sola query aggregata per tutte le attività richieste.
     *
     * @param list<int> $activityIds
     * @return array<int, int> minuti indicizzati per activity_id
     */
    public function sumMinutesByActivityIds(array $activityIds): array
    {
        $activityIds = $this->normalizeIds($activityIds);
        if ([] === $activityIds) {
            return [];
        }

        $totals = array_fill_keys($activityIds, 0);
        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            <<<'SQL'
SELECT activity_id,
       COALESCE(SUM(CAST((strftime('%s', ended_at) - strftime('%s', started_at)) / 60 AS INTEGER)), 0) AS total_minutes
FROM time_entry
WHERE ended_at IS NOT NULL
  AND activity_id IN (:activity_ids)
GROUP BY activity_id
SQL,
            ['activity_ids' => $activityIds],
            ['activity_ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            $totals[(int) $row['activity_id']] = (int) $row['total_minutes'];
        }

        return $totals;
    }

    /**
     * Restituisce, con una sola query, totale e dettaglio per autore delle ore.
     * I timer ancora attivi non fanno parte del consuntivato consolidato.
     *
     * @param list<int> $activityIds
     * @return array<int, array{total_minutes: int, contributors: list<array{user_id: int, display_name: string, total_minutes: int}>}>
     */
    public function summarizeMinutesByActivityAndUserIds(array $activityIds): array
    {
        $activityIds = $this->normalizeIds($activityIds);
        if ([] === $activityIds) {
            return [];
        }

        $summaries = [];
        foreach ($activityIds as $activityId) {
            $summaries[$activityId] = ['total_minutes' => 0, 'contributors' => []];
        }

        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            <<<'SQL'
SELECT time_entry.activity_id,
       app_user.id AS user_id,
       app_user.display_name,
       COALESCE(SUM(MAX(0, CAST((strftime('%s', time_entry.ended_at) - strftime('%s', time_entry.started_at)) / 60 AS INTEGER))), 0) AS total_minutes
FROM time_entry
INNER JOIN app_user ON app_user.id = time_entry.user_id
WHERE time_entry.ended_at IS NOT NULL
  AND time_entry.activity_id IN (:activity_ids)
GROUP BY time_entry.activity_id, app_user.id, app_user.display_name
ORDER BY time_entry.activity_id ASC, total_minutes DESC, app_user.display_name ASC
SQL,
            ['activity_ids' => $activityIds],
            ['activity_ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            $activityId = (int) ($row['activity_id'] ?? 0);
            if (!isset($summaries[$activityId])) {
                continue;
            }

            $minutes = (int) ($row['total_minutes'] ?? 0);
            $summaries[$activityId]['total_minutes'] += $minutes;
            $summaries[$activityId]['contributors'][] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'total_minutes' => $minutes,
            ];
        }

        return $summaries;
    }

    public function sumMinutesForUserBetween(User $user, DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
SELECT COALESCE(SUM(CAST((strftime('%s', ended_at) - strftime('%s', started_at)) / 60 AS INTEGER)), 0)
FROM time_entry
WHERE user_id = :user_id
  AND ended_at IS NOT NULL
  AND started_at >= :date_from
  AND started_at < :date_to
SQL,
            [
                'user_id' => $user->getId(),
                'date_from' => $from->format('Y-m-d H:i:s'),
                'date_to' => $to->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function sumCostCentsForProject(Project $project): int
    {
        $id = $project->getId();
        if (null === $id) {
            return 0;
        }

        return $this->sumCostCentsByProjectIds([$id])[$id] ?? 0;
    }

    /**
     * @param list<int> $projectIds
     * @return array<int, int> costi indicizzati per project_id
     */
    public function sumCostCentsByProjectIds(array $projectIds): array
    {
        $projectIds = $this->normalizeIds($projectIds);
        if ([] === $projectIds) {
            return [];
        }

        $totals = array_fill_keys($projectIds, 0);
        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            <<<'SQL'
SELECT activity.project_id,
       COALESCE(SUM(time_entry.cost_snapshot_cents), 0) AS total_cents
FROM time_entry
INNER JOIN activity ON activity.id = time_entry.activity_id
WHERE activity.project_id IN (:project_ids)
GROUP BY activity.project_id
SQL,
            ['project_ids' => $projectIds],
            ['project_ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            $totals[(int) $row['project_id']] = (int) $row['total_cents'];
        }

        return $totals;
    }

    public function overlaps(
        User $user,
        DateTimeImmutable $start,
        ?DateTimeImmutable $end,
        ?int $excludeId = null,
    ): bool {
        $builder = $this->createQueryBuilder('entry')
            ->select('COUNT(entry.id)')
            ->andWhere('entry.user = :user')
            ->andWhere('entry.startedAt < :end')
            ->andWhere('(entry.endedAt IS NULL OR entry.endedAt > :start)')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end ?? new DateTimeImmutable('9999-12-31'));

        if (null !== $excludeId) {
            $builder
                ->andWhere('entry.id <> :id')
                ->setParameter('id', $excludeId);
        }

        return 0 < (int) $builder->getQuery()->getSingleScalarResult();
    }

    private function createFilteredQueryBuilder(TimeEntrySearchCriteria $criteria): QueryBuilder
    {
        $builder = $this->createQueryBuilder('entry')
            ->join('entry.user', 'user')
            ->join('entry.activity', 'activity')
            ->join('activity.assignee', 'assignee')
            ->join('activity.project', 'project');

        if (null !== $criteria->projectId) {
            $builder
                ->andWhere('project.id = :projectId')
                ->setParameter('projectId', $criteria->projectId);
        }
        if (null !== $criteria->activityId) {
            $builder
                ->andWhere('activity.id = :activityId')
                ->setParameter('activityId', $criteria->activityId);
        }
        if (null !== $criteria->userId) {
            $builder
                ->andWhere('user.id = :userId')
                ->setParameter('userId', $criteria->userId);
        }
        if (null !== $criteria->startedFrom) {
            $builder
                ->andWhere('entry.startedAt >= :startedFrom')
                ->setParameter('startedFrom', $criteria->startedFrom);
        }
        if (null !== $criteria->startedBefore) {
            $builder
                ->andWhere('entry.startedAt < :startedBefore')
                ->setParameter('startedBefore', $criteria->startedBefore);
        }
        if (null !== $criteria->billable) {
            $builder
                ->andWhere('entry.billable = :billable')
                ->setParameter('billable', $criteria->billable);
        }

        return $builder;
    }

    /** @param list<int> $ids
     *  @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            if ($id > 0) {
                $normalized[$id] = $id;
            }
        }

        return array_values($normalized);
    }
}
