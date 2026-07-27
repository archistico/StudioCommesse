<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/** @extends ServiceEntityRepository<User> */
final class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $user, bool $flush = false): void
    {
        $this->getEntityManager()->persist($user);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function upgradePassword(
        PasswordAuthenticatedUserInterface $user,
        string $newHashedPassword,
    ): void {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(sprintf('Istanza utente non supportata: %s.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->save($user, true);
    }

    /** @return list<User> */
    public function findAllOrdered(): array
    {
        $result = $this->createQueryBuilder('user')
            ->orderBy('user.active', 'DESC')
            ->addOrderBy('user.displayName', 'ASC')
            ->getQuery()
            ->getResult();

        /** @var list<User> $result */
        return $result;
    }


    public function createSelectableQueryBuilder(?int $currentUserId = null): QueryBuilder
    {
        $builder = $this->createQueryBuilder('user')
            ->orderBy('user.displayName', 'ASC');

        if (null === $currentUserId) {
            return $builder->andWhere('user.active = :active')->setParameter('active', true);
        }

        return $builder
            ->andWhere('user.active = :active OR user.id = :currentUserId')
            ->setParameter('active', true)
            ->setParameter('currentUserId', $currentUserId);
    }

    public function countActiveByRole(UserRole $role): int
    {
        return (int) $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->andWhere('user.active = :active')
            ->andWhere('user.role = :role')
            ->setParameter('active', true)
            ->setParameter('role', $role->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveUsers(): int
    {
        return (int) $this->createQueryBuilder('user')
            ->select('COUNT(user.id)')
            ->andWhere('user.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
