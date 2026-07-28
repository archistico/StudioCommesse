<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ActivityStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Activity> */
final class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    public function save(Activity $activity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($activity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return list<Activity> */
    public function findForProject(Project $project): array
    {
        $result = $this->createQueryBuilder('activity')
            ->addSelect('assignee')
            ->join('activity.assignee', 'assignee')
            ->andWhere('activity.project = :project')
            ->setParameter('project', $project)
            ->orderBy('activity.status', 'ASC')
            ->addOrderBy('activity.dueAt', 'ASC')
            ->addOrderBy('activity.priority', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var list<Activity> $result */
        return $result;
    }

    public function createForProjectQueryBuilder(Project $project): QueryBuilder
    {
        return $this->createQueryBuilder('activity')
            ->andWhere('activity.project = :project')
            ->setParameter('project', $project)
            ->orderBy('activity.title', 'ASC');
    }

    /** @return list<Activity> */
    public function findForAssignee(User $user): array
    {
        $result = $this->createQueryBuilder('activity')
            ->addSelect('project', 'client')
            ->join('activity.project', 'project')
            ->join('project.client', 'client')
            ->andWhere('activity.assignee = :user')
            ->setParameter('user', $user)
            ->orderBy('activity.status', 'ASC')
            ->addOrderBy('activity.dueAt', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var list<Activity> $result */
        return $result;
    }


    /** @return list<Activity> */
    public function findAllForTimeReporting(?Project $project = null): array
    {
        $builder = $this->createQueryBuilder('activity')
            ->addSelect('project')
            ->join('activity.project', 'project')
            ->orderBy('project.code', 'DESC')
            ->addOrderBy('activity.title', 'ASC');

        if (null !== $project) {
            $builder
                ->andWhere('activity.project = :project')
                ->setParameter('project', $project);
        }

        $result = $builder->getQuery()->getResult();

        /** @var list<Activity> $result */
        return $result;
    }


    /** @return list<Activity> */
    public function findRecentlyUpdated(int $limit = 8): array
    {
        $result = $this->createQueryBuilder('activity')
            ->addSelect('project', 'client', 'assignee')
            ->join('activity.project', 'project')
            ->join('project.client', 'client')
            ->join('activity.assignee', 'assignee')
            ->andWhere('project.archivedAt IS NULL')
            ->orderBy('activity.updatedAt', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        /** @var list<Activity> $result */
        return $result;
    }

    public function countOpen(): int
    {
        return (int) $this->createQueryBuilder('activity')
            ->select('COUNT(activity.id)')
            ->andWhere('activity.status NOT IN (:closedStatuses)')
            ->setParameter('closedStatuses', [ActivityStatus::Completed->value, ActivityStatus::Cancelled->value])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOverdue(?DateTimeImmutable $now = null): int
    {
        return (int) $this->createQueryBuilder('activity')
            ->select('COUNT(activity.id)')
            ->andWhere('activity.dueAt < :now')
            ->andWhere('activity.status NOT IN (:closedStatuses)')
            ->setParameter('now', $now ?? new DateTimeImmutable())
            ->setParameter('closedStatuses', [ActivityStatus::Completed->value, ActivityStatus::Cancelled->value])
            ->getQuery()
            ->getSingleScalarResult();
    }
}
