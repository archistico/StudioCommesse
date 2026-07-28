<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\EventSubscriber\RequestIdSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final readonly class AuditLogger
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        #[Autowire(service: 'monolog.logger.security_audit')]
        private LoggerInterface $logger,
        private AuditPrivacyGuard $privacyGuard,
        private ?RequestStack $requestStack = null,
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
        $entry = $this->record(new AuditRecord(
            $action,
            $actorIdentifier,
            $subjectType,
            $subjectId,
            $details,
            $ipAddress,
        ));

        if (!$flush) {
            return;
        }

        $this->entityManager->flush();
        $this->mirror($entry);
    }

    public function record(AuditRecord $record): AuditLog
    {
        $request = $this->requestStack?->getCurrentRequest();
        $details = $this->enrichDetails($record->details, $request);
        $entry = new AuditLog(
            $record->action,
            $record->actorIdentifier,
            $record->subjectType,
            $record->subjectId,
            $details,
            $record->ipAddress ?? $request?->getClientIp(),
        );
        $this->entityManager->persist($entry);

        return $entry;
    }

    public function mirror(AuditLog $entry): void
    {
        $this->logger->info($entry->getAction()->value, $this->privacyGuard->logContext($entry));
    }

    /**
     * @param array<string, bool|float|int|string|null> $details
     * @return array<string, bool|float|int|string|null>
     */
    private function enrichDetails(array $details, ?Request $request): array
    {
        if (!$request instanceof Request) {
            return $details;
        }

        $requestId = $request->attributes->get(RequestIdSubscriber::ATTRIBUTE);
        $route = $request->attributes->get('_route');

        if (!array_key_exists('request_id', $details) && is_string($requestId) && '' !== $requestId) {
            $details['request_id'] = $requestId;
        }
        if (!array_key_exists('route', $details) && is_string($route) && '' !== $route) {
            $details['route'] = $route;
        }
        if (!array_key_exists('method', $details)) {
            $details['method'] = $request->getMethod();
        }

        return $details;
    }
}
