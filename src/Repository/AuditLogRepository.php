<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\Query\AuditPage;
use App\Query\AuditSearchCriteria;
use App\Query\AuditSummary;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AuditLog> */
final class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findPage(AuditSearchCriteria $criteria): AuditPage
    {
        $totalItems = (int) $this->createFilteredQueryBuilder($criteria)
            ->select('COUNT(audit.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $perPage = max(1, min(200, $criteria->perPage));
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = max(1, min($criteria->page, $totalPages));

        $result = $this->createFilteredQueryBuilder($criteria)
            ->orderBy('audit.occurredAt', 'DESC')
            ->addOrderBy('audit.id', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        /** @var list<AuditLog> $result */
        return new AuditPage($result, $totalItems, $page, $perPage, $totalPages);
    }

    public function summarize(AuditSearchCriteria $criteria): AuditSummary
    {
        $totalEvents = (int) $this->createFilteredQueryBuilder($criteria)
            ->select('COUNT(audit.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $securityEvents = (int) $this->createFilteredQueryBuilder($criteria)
            ->select('COUNT(audit.id)')
            ->andWhere('audit.action IN (:security_actions)')
            ->setParameter('security_actions', [AuditAction::LoginSucceeded->value, AuditAction::LoginFailed->value, AuditAction::LoginThrottled->value])
            ->getQuery()
            ->getSingleScalarResult();
        $failedLogins = (int) $this->createFilteredQueryBuilder($criteria)
            ->select('COUNT(audit.id)')
            ->andWhere('audit.action = :failed_login')
            ->setParameter('failed_login', AuditAction::LoginFailed->value)
            ->getQuery()
            ->getSingleScalarResult();
        $blockedLogins = (int) $this->createFilteredQueryBuilder($criteria)
            ->select('COUNT(audit.id)')
            ->andWhere('audit.action = :blocked_login')
            ->setParameter('blocked_login', AuditAction::LoginThrottled->value)
            ->getQuery()
            ->getSingleScalarResult();
        $actorCount = (int) $this->createFilteredQueryBuilder($criteria)
            ->select('COUNT(DISTINCT audit.actorIdentifier)')
            ->andWhere('audit.actorIdentifier IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return new AuditSummary($totalEvents, $securityEvents, $failedLogins, $blockedLogins, $actorCount);
    }

    /** @return list<AuditLog> */
    public function findForExport(AuditSearchCriteria $criteria, int $limit = 10_000): array
    {
        $result = $this->createFilteredQueryBuilder($criteria)
            ->orderBy('audit.occurredAt', 'DESC')
            ->addOrderBy('audit.id', 'DESC')
            ->setMaxResults(max(1, min(50_000, $limit)))
            ->getQuery()
            ->getResult();

        /** @var list<AuditLog> $result */
        return $result;
    }

    private function createFilteredQueryBuilder(AuditSearchCriteria $criteria): QueryBuilder
    {
        $builder = $this->createQueryBuilder('audit');

        if ($criteria->action instanceof AuditAction) {
            $builder->andWhere('audit.action = :action')->setParameter('action', $criteria->action->value);
        } elseif (null !== $criteria->group) {
            $actions = array_values(array_map(
                static fn (AuditAction $action): string => $action->value,
                array_filter(
                    AuditAction::cases(),
                    static fn (AuditAction $action): bool => $action->groupLabel() === $criteria->group,
                ),
            ));
            if ([] !== $actions) {
                $builder->andWhere('audit.action IN (:actions)')->setParameter('actions', $actions);
            }
        }

        if (null !== $criteria->actor) {
            $builder
                ->andWhere("LOWER(COALESCE(audit.actorIdentifier, '')) LIKE :actor")
                ->setParameter('actor', '%'.mb_strtolower($criteria->actor).'%');
        }
        if (null !== $criteria->requestId) {
            $builder
                ->andWhere('audit.details LIKE :request_id')
                ->setParameter('request_id', '%"request_id":"'.$criteria->requestId.'"%');
        }
        if (null !== $criteria->occurredFrom) {
            $builder->andWhere('audit.occurredAt >= :occurred_from')->setParameter('occurred_from', $criteria->occurredFrom);
        }
        if (null !== $criteria->occurredBefore) {
            $builder->andWhere('audit.occurredAt < :occurred_before')->setParameter('occurred_before', $criteria->occurredBefore);
        }

        return $builder;
    }
}
