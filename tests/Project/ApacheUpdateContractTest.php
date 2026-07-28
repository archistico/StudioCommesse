<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class ApacheUpdateContractTest extends TestCase
{
    public function testApachePackAndOfficialPublicRewriteRulesArePackaged(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
        $htaccess = (string) file_get_contents($root.'/public/.htaccess');

        self::assertSame('^1.0', $composer['require']['symfony/apache-pack'] ?? null);
        self::assertContains('symfony/apache-pack', array_column($lock['packages'] ?? [], 'name'));
        self::assertStringContainsString('DirectoryIndex index.php', $htaccess);
        self::assertStringContainsString('RewriteEngine On', $htaccess);
        self::assertStringContainsString('RewriteRule ^ %{ENV:BASE}/index.php [L]', $htaccess);
    }

    public function testUpdateStagesAutomaticallyWhenReleaseAndTargetCoincide(): void
    {
        $update = (string) file_get_contents(dirname(__DIR__, 2).'/scripts/update.ps1');

        self::assertStringContainsString('Invoke-SelfStagedUpdate', $update);
        self::assertStringContainsString("'studio-commesse-update-release-'", $update);
        self::assertStringContainsString('& $stagedScript -TargetDirectory $Destination -Confirm UPDATE -StagedRelease', $update);
        self::assertStringNotContainsString("Estrarre la nuova release in una cartella separata dall'installazione da aggiornare.", $update);
        self::assertStringContainsString('[AllowEmptyCollection()]', $update);
        self::assertStringContainsString('if ($staleEntries.Count -gt 0)', $update);
        self::assertStringContainsString('if ($null -eq $RelativePaths -or $RelativePaths.Count -eq 0)', $update);
    }
}
