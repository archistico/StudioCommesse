<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\EventSubscriber\RequestIdSubscriber;
use App\Service\AuditLogger;
use App\Service\AuditPrivacyGuard;
use App\Service\AuditRecord;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AuditLoggerContextTest extends TestCase
{
    public function testRecordAndMirrorIncludeRequestCorrelationMetadata(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $requestStack = new RequestStack();
        $request = Request::create('/commesse/42/modifica', 'POST');
        $request->attributes->set(RequestIdSubscriber::ATTRIBUTE, 'REQ-context-1234');
        $request->attributes->set('_route', 'app_project_edit');
        $requestStack->push($request);

        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(AuditLog::class));
        $logger->expects(self::once())->method('info')->with(
            AuditAction::ProjectUpdated->value,
            self::callback(static fn (array $context): bool => 'REQ-context-1234' === ($context['request_id'] ?? null)
                && 'app_project_edit' === ($context['route'] ?? null)
                && 'POST' === ($context['method'] ?? null)
                && ['name'] === ($context['detail_keys'] ?? null)
                && null !== ($context['actor_fingerprint'] ?? null)
                && !array_key_exists('details', $context)
                && !array_key_exists('actor', $context)),
        );

        $audit = new AuditLogger($entityManager, $logger, new AuditPrivacyGuard('test-secret'), $requestStack);
        $entry = $audit->record(new AuditRecord(
            AuditAction::ProjectUpdated,
            'socio',
            'App\\Entity\\Project',
            42,
            ['name' => 'Commessa'],
        ));

        self::assertSame('REQ-context-1234', $entry->getRequestId());
        self::assertSame('app_project_edit', $entry->getRoute());
        self::assertSame('POST', $entry->getHttpMethod());
        self::assertSame(['name' => 'Commessa'], $entry->getVisibleDetails());
        $audit->mirror($entry);
    }
}
