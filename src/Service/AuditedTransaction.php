<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AuditedTransaction
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AuditLogger $auditLogger,
    ) {
    }

    /**
     * Esegue mutazione e audit nella stessa transazione Doctrine.
     * Il primo flush assegna gli identificativi; il secondo persiste l'audit.
     * Il mirror Monolog viene scritto soltanto dopo il commit riuscito.
     *
     * @template T
     * @param callable(): T $mutation
     * @param callable(T): AuditRecord $auditFactory
     * @return T
     */
    public function execute(callable $mutation, callable $auditFactory): mixed
    {
        $this->configureSqliteConnection();
        $auditEntry = null;

        $result = $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use (
            $mutation,
            $auditFactory,
            &$auditEntry,
        ): mixed {
            $result = $mutation();
            $entityManager->flush();

            $auditEntry = $this->auditLogger->record($auditFactory($result));
            $entityManager->flush();

            return $result;
        });

        if ($auditEntry instanceof AuditLog) {
            $this->auditLogger->mirror($auditEntry);
        }

        return $result;
    }

    /**
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public function executeWithoutAudit(callable $operation): mixed
    {
        $this->configureSqliteConnection();

        return $this->entityManager->wrapInTransaction(
            static function (EntityManagerInterface $entityManager) use ($operation): mixed {
                $result = $operation();
                $entityManager->flush();

                return $result;
            },
        );
    }

    private function configureSqliteConnection(): void
    {
        $connection = $this->entityManager->getConnection();
        if (!($connection->getDatabasePlatform() instanceof SQLitePlatform)) {
            return;
        }

        $connection->executeStatement('PRAGMA busy_timeout = 5000');
        $connection->executeStatement('PRAGMA foreign_keys = ON');
    }
}
