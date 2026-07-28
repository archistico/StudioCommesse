<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class RobustnessContractTest extends TestCase
{
    public function testM92CRobustnessContractsArePresent(): void
    {
        $root = dirname(__DIR__, 2);
        $transaction = (string) file_get_contents($root.'/src/Service/AuditedTransaction.php');
        $audit = (string) file_get_contents($root.'/src/Service/AuditLogger.php');
        $timer = (string) file_get_contents($root.'/src/Service/TimerService.php');
        $timerLock = (string) file_get_contents($root.'/src/Service/TimerMutationLock.php');
        $attachmentLock = (string) file_get_contents($root.'/src/Service/AttachmentMutationLock.php');
        $storage = (string) file_get_contents($root.'/src/Service/AttachmentStorage.php');
        $manager = (string) file_get_contents($root.'/src/Service/AttachmentManager.php');
        $maintenance = (string) file_get_contents($root.'/src/EventSubscriber/MaintenanceModeSubscriber.php');
        $exceptions = (string) file_get_contents($root.'/src/EventSubscriber/DatabaseExceptionSubscriber.php');
        $requestId = (string) file_get_contents($root.'/src/EventSubscriber/RequestIdSubscriber.php');

        self::assertStringContainsString('wrapInTransaction', $transaction);
        self::assertStringContainsString('$entityManager->flush();', $transaction);
        self::assertStringContainsString('$this->auditLogger->record', $transaction);
        self::assertStringContainsString('$this->auditLogger->mirror', $transaction);
        self::assertStringContainsString("PRAGMA busy_timeout = 5000", $transaction);
        self::assertStringContainsString("PRAGMA foreign_keys = ON", $transaction);
        self::assertStringContainsString('public function record(AuditRecord $record): AuditLog', $audit);
        self::assertStringContainsString('TimerMutationLock', $timer);
        self::assertStringContainsString('acquireExclusive()', $timer);
        self::assertStringContainsString('ApplicationBusyException', $timerLock);
        self::assertStringContainsString('tryAcquireExclusive()', $timerLock);
        self::assertStringContainsString('ApplicationBusyException', $attachmentLock);
        self::assertStringContainsString('tryAcquireShared()', $attachmentLock);
        self::assertStringContainsString('public function quarantine', $storage);
        self::assertStringContainsString('public function restore(QuarantinedAttachment', $storage);
        self::assertStringContainsString('public function purge(QuarantinedAttachment', $storage);
        self::assertStringContainsString('$this->storage->restore($quarantined)', $manager);
        self::assertStringContainsString('tryAcquireShared()', $maintenance);
        self::assertStringContainsString('HTTP_SERVICE_UNAVAILABLE', $maintenance);
        self::assertStringContainsString('UniqueConstraintViolationException', $exceptions);
        self::assertStringContainsString('database is locked', $exceptions);
        self::assertStringContainsString("X-Request-ID", $requestId);

        $packager = (string) file_get_contents($root.'/scripts/package-release.ps1');
        $packageVerifier = (string) file_get_contents($root.'/scripts/verify-release-package.ps1');
        self::assertStringContainsString("'src/Service/AuditedTransaction.php'", $packager);
        self::assertStringContainsString("'docs/ROBUSTNESS.md'", $packager);
        self::assertStringContainsString("'tests/Project/RobustnessContractTest.php'", $packageVerifier);
        self::assertStringContainsString("'scripts/m92c-robustness-contract.php'", $packageVerifier);

        foreach (['error405.html.twig', 'error409.html.twig', 'error422.html.twig', 'error500.html.twig', 'error503.html.twig'] as $template) {
            self::assertFileExists($root.'/templates/bundles/TwigBundle/Exception/'.$template);
        }
    }

    public function testAuditedControllersNoLongerFlushBeforeWritingAudit(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['ProjectController.php', 'ActivityController.php', 'ClientController.php', 'UserController.php', 'EconomicsController.php', 'TimeEntryController.php'] as $controller) {
            $source = (string) file_get_contents($root.'/src/Controller/'.$controller);
            self::assertStringContainsString('AuditedTransaction', $source, $controller);
            self::assertDoesNotMatchRegularExpression('/->(?:save|remove)\([^;\n]*,\s*true\)/', $source, $controller.' non deve eseguire flush autonomi prima dell’audit.');
        }
    }
}
