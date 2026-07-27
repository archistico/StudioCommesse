<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class FixturesContractTest extends TestCase
{
    public function testFixturesAreExplicitAndNotPartOfSetup(): void
    {
        $setup = file_get_contents(__DIR__.'/../../scripts/setup.ps1');
        $loader = file_get_contents(__DIR__.'/../../scripts/load-fixtures.ps1');
        $server = file_get_contents(__DIR__.'/../../scripts/start-server.ps1');

        self::assertIsString($setup);
        self::assertIsString($loader);
        self::assertIsString($server);
        self::assertStringNotContainsString('app:fixtures:load', $setup);
        self::assertStringContainsString('[ValidateSet("dev", "test")]', $loader);
        self::assertStringContainsString('$environmentOption = "--env=$Environment"', $loader);
        self::assertStringContainsString('php bin/console doctrine:migrations:migrate --no-interaction $environmentOption', $loader);
        self::assertStringContainsString('php bin/console app:fixtures:load $environmentOption', $loader);
        self::assertStringContainsString('Remove-Item Env:APP_ENV', $loader);
        self::assertStringNotContainsString('--force', $loader);
        self::assertStringContainsString('$previousAppEnv = $env:APP_ENV', $server);
        self::assertStringContainsString('finally {', $server);
        self::assertStringContainsString('Remove-Item Env:APP_DEBUG', $server);
    }

    public function testDemoCommandIsRestrictedAndDefinesTheStandardProfile(): void
    {
        $source = file_get_contents(__DIR__.'/../../src/Command/LoadDemoFixturesCommand.php');

        self::assertIsString($source);
        self::assertStringContainsString("['dev', 'test']", $source);
        self::assertStringContainsString('InputOption::VALUE_NONE', $source);
        self::assertStringContainsString('private const DEMO_PROJECT_COUNT = 30;', $source);
        self::assertStringContainsString('private const DEMO_ACTIVITY_COUNT = 200;', $source);
        self::assertStringContainsString('private const DEMO_TIME_ENTRY_COUNT = 600;', $source);
        self::assertStringContainsString("'Rifacimento rete fognaria interna'", $source);
        self::assertStringContainsString("'Chiusura documentale'", $source);
        self::assertStringContainsString('private const CONTRIBUTOR_OFFSETS = [0, 1, 2, 0, 3, 1, 4, 2];', $source);
        self::assertStringContainsString('$worker = $users[$workerIndex];', $source);
        self::assertStringContainsString('removeManagedFixtureEntries', $source);
        self::assertStringContainsString('Registrazione manuale', file_get_contents(__DIR__.'/../Command/LoadDemoFixturesCommandTest.php'));
        self::assertStringContainsString('applyRateSnapshot', $source);
    }
}
