<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class PerformanceCapacityContractTest extends TestCase
{
    public function testDashboardUsesOneConsolidatedSummaryInsteadOfSevenCounterQueries(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/src/Controller/DashboardController.php');
        $repository = (string) file_get_contents($root.'/src/Repository/DashboardRepository.php');

        self::assertStringContainsString('DashboardRepository $dashboardRepository', $controller);
        self::assertStringContainsString('$dashboardRepository->summarize', $controller);
        foreach (['countOpenProjects()', 'countByStatus(', 'countOverdue()', 'countActiveClients()', 'countOpen()', 'countActiveUsers()'] as $legacyCall) {
            self::assertStringNotContainsString($legacyCall, $controller);
        }
        self::assertSame(10, substr_count($repository, '(SELECT COUNT(') + substr_count($repository, '(SELECT COALESCE(SUM('));
    }

    public function testCapacityBenchmarkIsIsolatedAndCoversAllAuthoritativeProfiles(): void
    {
        $root = dirname(__DIR__, 2);
        $script = (string) file_get_contents($root.'/scripts/benchmark-capacity.ps1');
        $seedCommand = (string) file_get_contents($root.'/src/Command/SeedPerformanceDatasetCommand.php');
        $benchmarkCommand = (string) file_get_contents($root.'/src/Command/BenchmarkCapacityCommand.php');

        self::assertStringContainsString('[ValidateSet(30, 200, 600)]', $script);
        self::assertStringContainsString('var/performance', $script);
        self::assertStringContainsString('$env:DATABASE_URL = "sqlite:///$databaseUrlPath"', $script);
        self::assertStringNotContainsString('doctrine:database:create', $script);
        self::assertStringContainsString('doctrine:migrations:migrate', $script);
        self::assertStringContainsString('--confirm=BENCHMARK', $script);
        self::assertStringContainsString('--enforce', $script);
        self::assertStringContainsString('M9.2-G CAPACITY BENCHMARK PASSED', $script);
        self::assertStringContainsString("name: 'app:performance:seed'", $seedCommand);
        self::assertStringContainsString("name: 'app:performance:benchmark'", $benchmarkCommand);
        foreach (['dashboard', 'commesse', 'attivita', 'ore', 'controllo', 'economia', 'report_mensile', 'audit', 'dettaglio_commessa', 'backup_restore'] as $metric) {
            self::assertStringContainsString($metric, $benchmarkCommand);
        }
        self::assertStringContainsString('@param iterable<Activity> $activities', $benchmarkCommand);
        self::assertStringContainsString('@return list<int>', $benchmarkCommand);
        self::assertStringContainsString('samples_ms: list<float>', $benchmarkCommand);

        $seeder = (string) file_get_contents($root.'/src/Service/PerformanceDatasetSeeder.php');
        self::assertStringContainsString('private function executePrepared(Statement $statement, array $parameters): void', $seeder);
        self::assertStringContainsString('ParameterType::NULL', $seeder);
        self::assertStringContainsString('ParameterType::INTEGER', $seeder);
        self::assertStringContainsString('$statement->bindValue($name, $value, $type);', $seeder);
        self::assertStringContainsString('$statement->executeStatement();', $seeder);
        self::assertStringNotContainsString('->executeStatement([', $seeder);
        self::assertStringContainsString('$now->format(\'Y/m\')', $seeder);
        self::assertStringContainsString('substr(hash(\'sha256\', sprintf(\'benchmark-attachment-%06d\', $id)), 0, 32)', $seeder);
        self::assertStringContainsString('file_put_contents($path, $content)', $seeder);
        self::assertStringNotContainsString('benchmark/%03d/documento-%06d.txt', $seeder);
    }

    public function testPerformanceIndexesMigrationAndDocumentationArePackaged(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = (string) file_get_contents($root.'/migrations/Version20260728160000.php');
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');
        $packager = (string) file_get_contents($root.'/scripts/package-release.ps1');
        $docs = (string) file_get_contents($root.'/docs/PERFORMANCE.md');

        foreach (['idx_project_active_due_code', 'idx_project_updated_at', 'idx_activity_assignee_status_due', 'idx_time_entry_started_ended', 'idx_time_entry_updated_at', 'idx_expense_date_project', 'idx_payment_date_project', 'idx_audit_actor_occurred', 'idx_audit_subject_occurred'] as $index) {
            self::assertStringContainsString($index, $migration);
        }
        self::assertStringContainsString('scripts/m92g-performance-contract.php', $validation);
        self::assertStringContainsString('benchmark-capacity.ps1', $validation);
        self::assertStringContainsString('scripts/benchmark-capacity.ps1', $packager);
        self::assertStringContainsString('tests/Project/PerformanceCapacityContractTest.php', $packager);
        self::assertStringContainsString('30, 200 e 600 commesse', $docs);
    }
}
