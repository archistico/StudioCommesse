<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class PhpRuntimeContractTest extends TestCase
{
    public function testFileinfoPreflightIsActionableAndRunsBeforeTheFullGate(): void
    {
        $root = dirname(__DIR__, 2);
        $runtime = (string) file_get_contents($root.'/scripts/php-runtime-contract.php');
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');
        $storage = (string) file_get_contents($root.'/scripts/attachment-storage-contract.php');

        self::assertStringContainsString("'fileinfo'", $runtime);
        self::assertStringContainsString('php_ini_loaded_file()', $runtime);
        self::assertStringContainsString('extension=fileinfo', $runtime);
        self::assertStringContainsString('php_fileinfo.dll', $runtime);
        self::assertStringContainsString("php -m | Select-String -Pattern '^fileinfo$'", $runtime);
        self::assertStringContainsString("require_once __DIR__.'/php-runtime-contract.php'", $storage);
        self::assertStringContainsString('scripts/php-runtime-contract.php', $validation);
        self::assertLessThan(
            strpos($validation, 'Invoke-Checked -Command "composer"'),
            strpos($validation, 'scripts/php-runtime-contract.php'),
        );
    }

    public function testCurrentRuntimeSatisfiesTheDeclaredContract(): void
    {
        self::assertGreaterThanOrEqual(80400, PHP_VERSION_ID);
        foreach (['ctype', 'fileinfo', 'iconv', 'mbstring', 'pdo', 'pdo_sqlite'] as $extension) {
            self::assertTrue(extension_loaded($extension), 'Estensione PHP mancante: '.$extension);
        }
    }
}
