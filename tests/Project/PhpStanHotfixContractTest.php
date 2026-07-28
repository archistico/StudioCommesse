<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class PhpStanHotfixContractTest extends TestCase
{
    public function testM92CHotfix2KeepsPhpstanNarrowingExplicitAndExhaustive(): void
    {
        $root = dirname(__DIR__, 2);
        $databaseSubscriber = (string) file_get_contents($root.'/src/EventSubscriber/DatabaseExceptionSubscriber.php');
        $maintenanceMode = (string) file_get_contents($root.'/src/Service/MaintenanceMode.php');
        $monthlyReport = (string) file_get_contents($root.'/src/Service/MonthlyReportService.php');
        $phpstan = (string) file_get_contents($root.'/phpstan.neon');
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');

        self::assertStringNotContainsString('$status = null;', $databaseSubscriber);
        self::assertStringNotContainsString('$template = null;', $databaseSubscriber);
        self::assertStringContainsString("} else {\n            return;\n        }", str_replace("\r\n", "\n", $databaseSubscriber));
        self::assertStringContainsString("/** @phpstan-impure */\n    public function isEnabled(): bool", str_replace("\r\n", "\n", $maintenanceMode));
        self::assertStringContainsString('AuditAction::TimeEntryCreated, AuditAction::TimeEntryUpdated, AuditAction::TimerStarted', $monthlyReport);
        self::assertStringContainsString('level: 8', $phpstan);
        self::assertStringNotContainsString('treatPhpDocTypesAsCertain: false', $phpstan);
        self::assertStringContainsString('scripts/m92c-hotfix2-phpstan-contract.php', $validation);
        self::assertStringContainsString('M9.2-H VALIDATION PASSED', $validation);
    }
}
