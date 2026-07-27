<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class AuditLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.security_audit')]
        private LoggerInterface $logger,
    ) {
    }

    /** @param array<string, bool|float|int|string|null> $details */
    public function log(
        AuditAction $action,
        ?string $actorIdentifier = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $details = [],
        ?string $ipAddress = null,
        bool $flush = true,
    ): void {
        $entry = new AuditLog(
            $action,
            $actorIdentifier,
            $subjectType,
            $subjectId,
            $details,
            $ipAddress,
        );

        $this->entityManager->persist($entry);

        if ($flush) {
            $this->entityManager->flush();
        }

        $this->logger->info($action->value, [
            'actor' => $actorIdentifier,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'details' => $details,
            'ip_address' => $ipAddress,
        ]);
    }
}
