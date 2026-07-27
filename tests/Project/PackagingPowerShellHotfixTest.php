<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class PackagingPowerShellHotfixTest extends TestCase
{
    public function testM92AHotfix1UsesValidForeachCommandExpression(): void
    {
        $root = dirname(__DIR__, 2);
        $script = (string) file_get_contents($root.'/scripts/package-release.ps1');
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');
        $services = (string) file_get_contents($root.'/config/services.yaml');

        self::assertStringContainsString(
            'foreach ($item in (Get-ChildItem -LiteralPath $Directory -Force)) {',
            $script,
        );
        self::assertStringNotContainsString(
            'foreach ($item in Get-ChildItem -LiteralPath $Directory -Force) {',
            $script,
        );
        self::assertStringContainsString("app.version: '0.9.2-M9.2-A-HF1'", $services);
        self::assertStringContainsString('M9.2-A HOTFIX 1 VALIDATION PASSED', $validation);
    }
}
