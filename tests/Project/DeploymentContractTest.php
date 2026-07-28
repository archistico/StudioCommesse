<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class DeploymentContractTest extends TestCase
{
    public function testPreflightSeparatesInstallUpdateAndCleanPackageChecks(): void
    {
        $preflight = (string) file_get_contents(dirname(__DIR__, 2).'/scripts/release-preflight.ps1');

        self::assertStringContainsString("[ValidateSet('Install', 'Update', 'Package')]", $preflight);
        self::assertStringContainsString("if (\$Mode -eq 'Package')", $preflight);
        self::assertStringContainsString("if (\$Mode -eq 'Update')", $preflight);
        self::assertStringContainsString("'var/studio_commesse.db'", $preflight);
        self::assertStringContainsString("'vendor'", $preflight);
        self::assertStringContainsString("'public/vendor'", $preflight);
    }

    public function testSetupRunsTheSharedPreflightBeforeInstallingDependencies(): void
    {
        $setup = (string) file_get_contents(dirname(__DIR__, 2).'/scripts/setup.ps1');
        $preflightPosition = strpos($setup, "release-preflight.ps1') -Mode Install");
        $composerPosition = strpos($setup, 'Invoke-Checked -Command "composer"');

        self::assertIsInt($preflightPosition);
        self::assertIsInt($composerPosition);
        self::assertLessThan($composerPosition, $preflightPosition);
    }

    public function testUpdateCreatesVerifiedDataAndCodeBackupsBeforeMaintenance(): void
    {
        $update = (string) file_get_contents(dirname(__DIR__, 2).'/scripts/update.ps1');

        $backupPosition = strpos($update, "scripts/backup.ps1') -DestinationDirectory");
        $verifyPosition = strpos($update, "scripts/verify-backup.ps1') -Archive");
        $codePosition = strpos($update, "scripts/package-release.ps1') -OutputPath \$previousCodeArchive");
        $maintenancePosition = strpos($update, 'app:maintenance:enable');

        self::assertIsInt($backupPosition);
        self::assertIsInt($verifyPosition);
        self::assertIsInt($codePosition);
        self::assertIsInt($maintenancePosition);
        self::assertLessThan($maintenancePosition, $backupPosition);
        self::assertLessThan($maintenancePosition, $verifyPosition);
        self::assertLessThan($maintenancePosition, $codePosition);
    }

    public function testUpdateHasAutomaticRollbackAndKeepsMaintenanceOnRollbackFailure(): void
    {
        $update = (string) file_get_contents(dirname(__DIR__, 2).'/scripts/update.ps1');

        self::assertStringContainsString('Restore-CodeArchive', $update);
        self::assertStringContainsString('Invoke-SelfStagedUpdate', $update);
        self::assertStringContainsString('app:backup:restore', $update);
        self::assertStringContainsString('ROLLBACK AUTOMATICO NON COMPLETATO.', $update);
        self::assertStringContainsString('La modalità manutenzione resta attiva', $update);
        self::assertStringContainsString('ROLLBACK.txt', $update);
        self::assertStringContainsString('[AllowEmptyCollection()]', $update);
        self::assertStringContainsString('if ($staleEntries.Count -gt 0)', $update);

        foreach (glob(dirname(__DIR__, 2).'/scripts/*.ps1') ?: [] as $scriptPath) {
            $script = (string) file_get_contents($scriptPath);
            self::assertDoesNotMatchRegularExpression(
                '/[\x{2018}\x{2019}\x{201C}\x{201D}]/u',
                $script,
                basename($scriptPath).' contiene virgolette tipografiche non sicure per PowerShell.',
            );
        }
    }

    public function testDeploymentFilesArePackagedDocumentedAndValidated(): void
    {
        $root = dirname(__DIR__, 2);
        $packager = (string) file_get_contents($root.'/scripts/package-release.ps1');
        $verifier = (string) file_get_contents($root.'/scripts/verify-release-package.ps1');
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');
        $readme = (string) file_get_contents($root.'/README.md');

        foreach (['scripts/release-preflight.ps1', 'scripts/update.ps1', 'scripts/install-smoke.ps1', 'scripts/m92f-deployment-contract.php', 'tests/Project/DeploymentContractTest.php', 'tests/Project/ApacheUpdateContractTest.php', 'scripts/m92f1-apache-update-contract.php', 'docs/INSTALL_UPDATE.md', 'docs/APACHE.md', 'public/.htaccess'] as $required) {
            self::assertStringContainsString($required, $packager);
            self::assertStringContainsString($required, $verifier);
        }
        self::assertStringContainsString('install-smoke.ps1', $validation);
        self::assertStringContainsString('M9.2-H VALIDATION PASSED', $validation);
        self::assertStringContainsString('update.ps1', $readme);
        self::assertStringNotContainsString('M9.2-F', $readme);
    }
}
