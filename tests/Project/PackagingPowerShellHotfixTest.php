<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class PackagingPowerShellHotfixTest extends TestCase
{
    public function testM92AHotfix1KeepsValidatedPowerShellLineContinuation(): void
    {
        $root = dirname(__DIR__, 2);
        $script = (string) file_get_contents($root.'/scripts/package-release.ps1');

        self::assertStringContainsString(
            'foreach ($item in (Get-ChildItem -LiteralPath $Directory -Force)) {',
            $script,
        );
        self::assertStringContainsString(
            "IsNullOrWhiteSpace(\$EntryName) -or\n        \$EntryName.StartsWith('/') -or",
            $script,
        );
        self::assertStringNotContainsString(
            "IsNullOrWhiteSpace(\$EntryName)\n        -or",
            $script,
        );
    }

    public function testCurrentHotfixVersionAndGateAreAligned(): void
    {
        $root = dirname(__DIR__, 2);
        $services = (string) file_get_contents($root.'/config/services.yaml');
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');

        self::assertStringContainsString("app.version: '0.9.2-M9.2-H'", $services);
        self::assertStringContainsString('M9.2-H VALIDATION PASSED', $validation);
    }
}
