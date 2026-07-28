<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class PackagingContractTest extends TestCase
{
    public function testM92APackageCommandExcludesRuntimeAndLocalData(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root.'/scripts/package-release.ps1');
        self::assertIsString($script);

        foreach (["'.env.local'", "'vendor'", "'node_modules'", "'var'", "'backups'", "'dist'", "'public/vendor/'"] as $needle) {
            self::assertStringContainsString($needle, $script);
        }
        self::assertStringContainsString('Assert-SafeArchiveEntryName', $script);
        self::assertStringContainsString('requiredEntries', $script);
        self::assertStringContainsString('src/Kernel.php', $script);
        self::assertStringContainsString('src/Controller/UserController.php', $script);
        self::assertStringContainsString('verify-release-package.ps1', $script);
        self::assertStringContainsString('StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip', $script);
    }

    public function testM92AAuthoritativeDocumentsAndVersionsAreAligned(): void
    {
        $root = dirname(__DIR__, 2);
        $services = (string) file_get_contents($root.'/config/services.yaml');
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');
        $readme = (string) file_get_contents($root.'/README.md');
        $handoff = (string) file_get_contents($root.'/docs/PROJECT_HANDOFF.md');

        self::assertStringContainsString("app.version: '0.9.2-M9.2-H'", $services);
        self::assertStringContainsString('M9.2-H VALIDATION PASSED', $validation);
        self::assertStringContainsString('## Requisiti', $readme);
        self::assertStringContainsString('## Installazione', $readme);
        self::assertStringContainsString('## Script principali', $readme);
        self::assertStringNotContainsString('Candidate corrente', $readme);
        self::assertStringNotContainsString('Ultima baseline validata', $readme);
        self::assertStringContainsString('StudioCommesse_M9.2-G_Performance_Capacity_Fix3.zip', $handoff);
        self::assertStringContainsString('StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip', $handoff);
        self::assertFileExists($root.'/docs/PACKAGING.md');
    }
}
