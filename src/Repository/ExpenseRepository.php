<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Expense> */
final class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    public function save(Expense $expense, bool $flush = false): void
    {
        $this->getEntityManager()->persist($expense);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Expense $expense, bool $flush = false): void
    {
        $this->getEntityManager()->remove($expense);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return list<Expense> */
    public function findForProject(Project $project): array
    {
        $result = $this->createQueryBuilder('expense')
            ->addSelect('activity', 'recordedBy')
            ->leftJoin('expense.activity', 'activity')
            ->join('expense.recordedBy', 'recordedBy')
            ->andWhere('expense.project = :project')
            ->setParameter('project', $project)
            ->orderBy('expense.spentOn', 'DESC')
            ->addOrderBy('expense.id', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var list<Expense> $result */
        return $result;
    }

    /** @return list<Expense> */
    public function findForProjectAndRecorder(Project $project, User $user): array
    {
        $result = $this->createQueryBuilder('expense')
            ->addSelect('activity', 'recordedBy')
            ->leftJoin('expense.activity', 'activity')
            ->join('expense.recordedBy', 'recordedBy')
            ->andWhere('expense.project = :project')
            ->andWhere('expense.recordedBy = :recordedBy')
            ->setParameter('project', $project)
            ->setParameter('recordedBy', $user)
            ->orderBy('expense.spentOn', 'DESC')
            ->addOrderBy('expense.id', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var list<Expense> $result */
        return $result;
    }

    public function sumCentsForProjectAndRecorder(Project $project, User $user): int
    {
        return (int) $this->createQueryBuilder('expense')
            ->select('COALESCE(SUM(expense.amountCents), 0)')
            ->andWhere('expense.project = :project')
            ->andWhere('expense.recordedBy = :recordedBy')
            ->setParameter('project', $project)
            ->setParameter('recordedBy', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumCentsForProject(Project $project): int
    {
        $id = $project->getId();
        if (null === $id) {
            return 0;
        }

        return $this->sumCentsByProjectIds([$id])[$id] ?? 0;
    }

    /**
     * @param list<int> $projectIds
     * @return array<int, int> importi indicizzati per project_id
     */
    public function sumCentsByProjectIds(array $projectIds): array
    {
        $projectIds = array_values(array_unique(array_filter($projectIds, static fn (int $id): bool => $id > 0)));
        if ([] === $projectIds) {
            return [];
        }

        $totals = array_fill_keys($projectIds, 0);
        $rows = $this->getEntityManager()->getConnection()->executeQuery(
            <<<'SQL'
SELECT project_id, COALESCE(SUM(amount_cents), 0) AS total_cents
FROM expense
WHERE project_id IN (:project_ids)
GROUP BY project_id
SQL,
            ['project_ids' => $projectIds],
            ['project_ids' => ArrayParameterType::INTEGER],
        )->fetchAllAssociative();

        foreach ($rows as $row) {
            $totals[(int) $row['project_id']] = (int) $row['total_cents'];
        }

        return $totals;
    }

}