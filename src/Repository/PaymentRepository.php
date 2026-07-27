<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Payment> */
final class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function save(Payment $payment, bool $flush = false): void
    {
        $this->getEntityManager()->persist($payment);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Payment $payment, bool $flush = false): void
    {
        $this->getEntityManager()->remove($payment);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return list<Payment> */
    public function findForProject(Project $project): array
    {
        $result = $this->createQueryBuilder('payment')
            ->addSelect('recordedBy')
            ->join('payment.recordedBy', 'recordedBy')
            ->andWhere('payment.project = :project')
            ->setParameter('project', $project)
            ->orderBy('payment.paidOn', 'DESC')
            ->addOrderBy('payment.id', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var list<Payment> $result */
        return $result;
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
FROM payment
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