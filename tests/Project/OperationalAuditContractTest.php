<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class OperationalAuditContractTest extends TestCase
{
    public function testOperationalAuditArtifactsArePackagedAndValidated(): void
    {
        $root = dirname(__DIR__, 2);
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');
        $packager = (string) file_get_contents($root.'/scripts/package-release.ps1');
        $verifier = (string) file_get_contents($root.'/scripts/verify-release-package.ps1');
        $readme = (string) file_get_contents($root.'/README.md');
        $security = (string) file_get_contents($root.'/config/packages/security.yaml');

        self::assertFileExists($root.'/src/Controller/AuditController.php');
        self::assertFileExists($root.'/src/Query/AuditSearchCriteria.php');
        self::assertFileExists($root.'/src/Query/AuditPage.php');
        self::assertFileExists($root.'/src/Query/AuditSummary.php');
        self::assertFileExists($root.'/templates/audit/index.html.twig');
        self::assertFileExists($root.'/docs/OPERATIONAL_AUDIT.md');
        self::assertFileExists($root.'/scripts/m92d-operational-audit-contract.php');
        self::assertStringContainsString("path: '^/audit(?:/|$)'", $security);
        self::assertStringContainsString('scripts/m92d-operational-audit-contract.php', $validation);
        self::assertStringContainsString('M9.2-H VALIDATION PASSED', $validation);
        foreach ([
            'src/Controller/AuditController.php',
            'templates/audit/index.html.twig',
            'tests/Controller/OperationalAuditTest.php',
            'tests/Service/AuditLoggerContextTest.php',
            'tests/Project/OperationalAuditContractTest.php',
            'scripts/m92d-operational-audit-contract.php',
            'docs/OPERATIONAL_AUDIT.md',
        ] as $file) {
            self::assertStringContainsString("'{$file}'", $packager, $file);
            self::assertStringContainsString("'{$file}'", $verifier, $file);
        }
        self::assertDoesNotMatchRegularExpression('/\bM\d+(?:\.\d+)*(?:-[A-Z0-9.]+)?\b|baseline|candidate|VALIDATION PASSED/i', $readme);
    }
}
