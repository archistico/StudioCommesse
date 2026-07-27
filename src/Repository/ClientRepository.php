<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Client> */
final class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    public function save(Client $client, bool $flush = false): void
    {
        $this->getEntityManager()->persist($client);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return list<Client> */
    public function findFiltered(?string $query, bool $includeArchived): array
    {
        $builder = $this->createQueryBuilder('client')
            ->orderBy('client.archivedAt', 'ASC')
            ->addOrderBy('client.name', 'ASC');

        if (!$includeArchived) {
            $builder->andWhere('client.archivedAt IS NULL');
        }

        $normalizedQuery = null === $query ? '' : trim($query);
        if ('' !== $normalizedQuery) {
            $builder
                ->andWhere('LOWER(client.name) LIKE :query OR LOWER(client.contactPerson) LIKE :query OR LOWER(client.email) LIKE :query')
                ->setParameter('query', '%'.mb_strtolower($normalizedQuery).'%');
        }

        $result = $builder->getQuery()->getResult();

        /** @var list<Client> $result */
        return $result;
    }

    public function createSelectableQueryBuilder(?int $currentClientId = null): QueryBuilder
    {
        $builder = $this->createQueryBuilder('client')
            ->orderBy('client.name', 'ASC');

        if (null === $currentClientId) {
            return $builder->andWhere('client.archivedAt IS NULL');
        }

        return $builder
            ->andWhere('client.archivedAt IS NULL OR client.id = :currentClientId')
            ->setParameter('currentClientId', $currentClientId);
    }

    public function countActiveClients(): int
    {
        return (int) $this->createQueryBuilder('client')
            ->select('COUNT(client.id)')
            ->andWhere('client.archivedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
