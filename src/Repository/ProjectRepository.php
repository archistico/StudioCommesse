<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\ProjectStatus;
use App\Query\ProjectSearchCriteria;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Project> */
final class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    public function save(Project $project, bool $flush = false): void
    {
        $this->getEntityManager()->persist($project);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return list<Project> */
    public function findFiltered(ProjectSearchCriteria $criteria): array
    {
        $builder = $this->createQueryBuilder('project')
            ->addSelect('client', 'responsible')
            ->join('project.client', 'client')
            ->join('project.responsible', 'responsible')
            ->orderBy('project.archivedAt', 'ASC')
            ->addOrderBy('project.dueDate', 'ASC')
            ->addOrderBy('project.code', 'DESC');

        if (!$criteria->includeArchived) {
            $builder->andWhere('project.archivedAt IS NULL');
        }

        if (null !== $criteria->query && '' !== trim($criteria->query)) {
            $builder
                ->andWhere('LOWER(project.code) LIKE :query OR LOWER(project.name) LIKE :query OR LOWER(client.name) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower(trim($criteria->query)).'%');
        }

        if (null !== $criteria->status) {
            $builder
                ->andWhere('project.status = :status')
                ->setParameter('status', $criteria->status->value);
        }

        if (null !== $criteria->priority) {
            $builder
                ->andWhere('project.priority = :priority')
                ->setParameter('priority', $criteria->priority->value);
        }

        if (null !== $criteria->responsibleId) {
            $builder
                ->andWhere('responsible.id = :responsibleId')
                ->setParameter('responsibleId', $criteria->responsibleId);
        }

        $result = $builder->getQuery()->getResult();

        /** @var list<Project> $result */
        return $result;
    }

    /** @return list<Project> */
    public function findForClient(Client $client, bool $includeArchived = true): array
    {
        $builder = $this->createQueryBuilder('project')
            ->addSelect('responsible')
            ->join('project.responsible', 'responsible')
            ->andWhere('project.client = :client')
            ->setParameter('client', $client)
            ->orderBy('project.archivedAt', 'ASC')
            ->addOrderBy('project.code', 'DESC');

        if (!$includeArchived) {
            $builder->andWhere('project.archivedAt IS NULL');
        }

        $result = $builder->getQuery()->getResult();

        /** @var list<Project> $result */
        return $result;
    }

    public function countNonArchivedForClient(Client $client): int
    {
        return (int) $this->createQueryBuilder('project')
            ->select('COUNT(project.id)')
            ->andWhere('project.client = :client')
            ->andWhere('project.archivedAt IS NULL')
            ->setParameter('client', $client)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countNonArchivedForResponsible(User $responsible): int
    {
        return (int) $this->createQueryBuilder('project')
            ->select('COUNT(project.id)')
            ->andWhere('project.responsible = :responsible')
            ->andWhere('project.archivedAt IS NULL')
            ->setParameter('responsible', $responsible)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOpenProjects(): int
    {
        return (int) $this->createQueryBuilder('project')
            ->select('COUNT(project.id)')
            ->andWhere('project.archivedAt IS NULL')
            ->andWhere('project.status NOT IN (:closedStatuses)')
            ->setParameter('closedStatuses', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(ProjectStatus $status): int
    {
        return (int) $this->createQueryBuilder('project')
            ->select('COUNT(project.id)')
            ->andWhere('project.archivedAt IS NULL')
            ->andWhere('project.status = :status')
            ->setParameter('status', $status->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOverdue(?DateTimeImmutable $today = null): int
    {
        return (int) $this->createQueryBuilder('project')
            ->select('COUNT(project.id)')
            ->andWhere('project.archivedAt IS NULL')
            ->andWhere('project.dueDate < :today')
            ->andWhere('project.status NOT IN (:closedStatuses)')
            ->setParameter('today', $today ?? new DateTimeImmutable('today'))
            ->setParameter('closedStatuses', [ProjectStatus::Completed->value, ProjectStatus::Cancelled->value])
            ->getQuery()
            ->getSingleScalarResult();
    }


    /** @return list<Project> */
    public function findAllForTimeReporting(): array
    {
        $result = $this->createQueryBuilder('project')
            ->addSelect('client')
            ->join('project.client', 'client')
            ->orderBy('project.archivedAt', 'ASC')
            ->addOrderBy('project.code', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var list<Project> $result */
        return $result;
    }

    /** @return list<Project> */
    public function findRecentActive(int $limit = 6): array
    {
        $result = $this->createQueryBuilder('project')
            ->addSelect('client', 'responsible')
            ->join('project.client', 'client')
            ->join('project.responsible', 'responsible')
            ->andWhere('project.archivedAt IS NULL')
            ->orderBy('project.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        /** @var list<Project> $result */
        return $result;
    }
    /** @return list<Project> */
    public function findForEconomics(): array
    {
        $result = $this->createQueryBuilder('project')
            ->addSelect('client', 'responsible')
            ->join('project.client', 'client')
            ->join('project.responsible', 'responsible')
            ->andWhere('project.archivedAt IS NULL')
            ->orderBy('project.dueDate', 'ASC')
            ->addOrderBy('project.code', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var list<Project> $result */
        return $result;
    }

}
