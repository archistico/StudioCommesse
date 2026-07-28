<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\Service\AuditLogger;
use App\Service\AuditPrivacyGuard;
use App\Service\AuditRecord;
use App\Service\AuditedTransaction;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AuditedTransactionTest extends TestCase
{
    public function testMutationAndAuditAreFlushedBeforeTheMirrorIsWritten(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $connection = $this->createMock(Connection::class);
        $platform = new SQLitePlatform();
        $logger = $this->createMock(LoggerInterface::class);
        $events = [];

        $entityManager->method('getConnection')->willReturn($connection);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->expects(self::exactly(2))->method('executeStatement');
        $entityManager->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $callback): mixed => $callback($entityManager),
        );
        $entityManager->expects(self::exactly(2))->method('flush')->willReturnCallback(
            static function () use (&$events): void {
                $events[] = 'flush';
            },
        );
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(AuditLog::class));
        $logger->expects(self::once())->method('info')->willReturnCallback(
            static function () use (&$events): void {
                $events[] = 'mirror';
            },
        );

        $service = new AuditedTransaction($entityManager, new AuditLogger($entityManager, $logger, new AuditPrivacyGuard('test-secret')));
        $result = $service->execute(
            static function () use (&$events): string {
                $events[] = 'mutation';

                return 'ok';
            },
            static fn (string $value): AuditRecord => new AuditRecord(
                AuditAction::ClientUpdated,
                'socio',
                'Client',
                10,
                ['result' => $value],
            ),
        );

        self::assertSame('ok', $result);
        self::assertSame(['mutation', 'flush', 'flush', 'mirror'], $events);
    }

    public function testMirrorIsNotWrittenWhenTheAuditFlushFails(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $connection = $this->createStub(Connection::class);
        $logger = $this->createMock(LoggerInterface::class);
        $flushCount = 0;

        $entityManager->method('getConnection')->willReturn($connection);
        $connection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());
        $entityManager->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $callback): mixed => $callback($entityManager),
        );
        $entityManager->method('flush')->willReturnCallback(
            static function () use (&$flushCount): void {
                ++$flushCount;
                if (2 === $flushCount) {
                    throw new \RuntimeException('audit flush failed');
                }
            },
        );
        $logger->expects(self::never())->method('info');

        $service = new AuditedTransaction($entityManager, new AuditLogger($entityManager, $logger, new AuditPrivacyGuard('test-secret')));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('audit flush failed');
        $service->execute(
            static fn (): string => 'ok',
            static fn (): AuditRecord => new AuditRecord(AuditAction::ClientUpdated),
        );
    }
}
