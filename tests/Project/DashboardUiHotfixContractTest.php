<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class DashboardUiHotfixContractTest extends TestCase
{
    public function testHotfixFourContractIsPackagedAndExecuted(): void
    {
        $root = dirname(__DIR__, 2);
        $contract = file_get_contents($root.'/scripts/m92c-hotfix4-dashboard-contract.php');
        $validation = file_get_contents($root.'/scripts/validate.ps1');
        $packager = file_get_contents($root.'/scripts/package-release.ps1');
        $verifier = file_get_contents($root.'/scripts/verify-release-package.ps1');

        self::assertIsString($contract);
        self::assertIsString($validation);
        self::assertIsString($packager);
        self::assertIsString($verifier);
        self::assertStringContainsString("app.version: '0.9.2-M9.2-H'", $contract);
        self::assertStringContainsString('scripts/m92c-hotfix4-dashboard-contract.php', $validation);
        self::assertStringContainsString('M9.2-H VALIDATION PASSED', $validation);
        self::assertStringContainsString("'scripts/m92c-hotfix4-dashboard-contract.php'", $packager);
        self::assertStringContainsString("'tests/Project/DashboardUiHotfixContractTest.php'", $packager);
        self::assertStringContainsString("'scripts/m92c-hotfix4-dashboard-contract.php'", $verifier);
        self::assertStringContainsString("'tests/Project/DashboardUiHotfixContractTest.php'", $verifier);
    }
}
