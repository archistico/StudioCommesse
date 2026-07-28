<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'services' => (string) file_get_contents($root.'/config/services.yaml'),
    'dashboard_controller' => (string) file_get_contents($root.'/src/Controller/DashboardController.php'),
    'dashboard_repository' => (string) file_get_contents($root.'/src/Repository/DashboardRepository.php'),
    'profile' => (string) file_get_contents($root.'/src/Performance/CapacityProfile.php'),
    'seeder' => (string) file_get_contents($root.'/src/Service/PerformanceDatasetSeeder.php'),
    'seed_command' => (string) file_get_contents($root.'/src/Command/SeedPerformanceDatasetCommand.php'),
    'benchmark_command' => (string) file_get_contents($root.'/src/Command/BenchmarkCapacityCommand.php'),
    'benchmark_script' => (string) file_get_contents($root.'/scripts/benchmark-capacity.ps1'),
    'migration' => (string) file_get_contents($root.'/migrations/Version20260728160000.php'),
    'validation' => (string) file_get_contents($root.'/scripts/validate.ps1'),
    'packager' => (string) file_get_contents($root.'/scripts/package-release.ps1'),
    'docs' => (string) file_get_contents($root.'/docs/PERFORMANCE.md'),
];

$checks = [
    [str_contains($files['services'], "app.version: '0.9.2-M9.2-H'"), 'versione M9.2-G'],
    [str_contains($files['dashboard_controller'], 'DashboardRepository $dashboardRepository'), 'dashboard consolidata'],
    [str_contains($files['dashboard_controller'], '$dashboardRepository->summarize'), 'chiamata summary dashboard'],
    [!str_contains($files['dashboard_controller'], 'countOpenProjects()'), 'assenza contatori dashboard separati'],
    [substr_count($files['dashboard_repository'], '(SELECT COUNT(') + substr_count($files['dashboard_repository'], '(SELECT COALESCE(SUM(') === 10, 'dieci metriche dashboard in una query'],
    [str_contains($files['profile'], 'case Small = 30;') && str_contains($files['profile'], 'case Medium = 200;') && str_contains($files['profile'], 'case Large = 600;'), 'profili 30/200/600'],
    [str_contains($files['seeder'], 'Dataset deterministico M9.2-G.'), 'dataset deterministico'],
    [str_contains($files['seed_command'], "name: 'app:performance:seed'"), 'comando seed'],
    [str_contains($files['benchmark_command'], "name: 'app:performance:benchmark'"), 'comando benchmark'],
    [str_contains($files['benchmark_command'], "'backup_restore'"), 'benchmark ripristino'],
    [str_contains($files['benchmark_command'], '@param iterable<Activity> $activities') && str_contains($files['benchmark_command'], '@return list<int>'), 'tipi lista identificativi attività'],
    [str_contains($files['benchmark_command'], 'samples_ms: list<float>'), 'shape metriche benchmark'],
    [str_contains($files['seeder'], 'private function executePrepared(Statement $statement, array $parameters): void'), 'helper prepared statement DBAL 4'],
    [str_contains($files['seeder'], 'ParameterType::NULL') && str_contains($files['seeder'], 'ParameterType::INTEGER') && str_contains($files['seeder'], '$statement->bindValue($name, $value, $type);') && str_contains($files['seeder'], '$statement->executeStatement();'), 'binding statement DBAL 4'],
    [!str_contains($files['seeder'], '->executeStatement(['), 'assenza parametri su Statement::executeStatement'],
    [str_contains($files['seeder'], '$now->format(\'Y/m\')') && str_contains($files['seeder'], 'substr(hash(\'sha256\', sprintf(\'benchmark-attachment-%06d\', $id)), 0, 32)'), 'chiavi allegati benchmark compatibili con backup'],
    [str_contains($files['seeder'], 'file_put_contents($path, $content)') && !str_contains($files['seeder'], 'benchmark/%03d/documento-%06d.txt'), 'file fisici allegati benchmark coerenti'],
    [str_contains($files['benchmark_script'], '[ValidateSet(30, 200, 600)]'), 'script profili'],
    [str_contains($files['benchmark_script'], '$env:DATABASE_URL = "sqlite:///$databaseUrlPath"'), 'database isolato'],
    [!str_contains($files['benchmark_script'], 'doctrine:database:create'), 'assenza database:create non supportato da SQLite'],
    [str_contains($files['benchmark_script'], 'doctrine:migrations:migrate'), 'creazione SQLite tramite migrazioni'],
    [str_contains($files['benchmark_script'], 'M9.2-G CAPACITY BENCHMARK PASSED'), 'gate capacità'],
    [str_contains($files['validation'], 'scripts/m92g-performance-contract.php'), 'contratto nel gate'],
    [str_contains($files['validation'], 'benchmark-capacity.ps1'), 'benchmark nel gate'],
    [str_contains($files['validation'], 'M9.2-H VALIDATION PASSED'), 'gate M9.2-G'],
    [str_contains($files['packager'], 'scripts/benchmark-capacity.ps1'), 'benchmark nel packaging'],
    [str_contains($files['packager'], 'migrations/Version20260728160000.php'), 'migrazione nel packaging'],
    [str_contains($files['docs'], '30, 200 e 600 commesse'), 'documentazione capacità'],
];

foreach (['idx_project_active_due_code', 'idx_project_updated_at', 'idx_activity_assignee_status_due', 'idx_activity_updated_at', 'idx_time_entry_started_ended', 'idx_time_entry_updated_at', 'idx_expense_date_project', 'idx_payment_date_project', 'idx_audit_actor_occurred', 'idx_audit_subject_occurred'] as $index) {
    $checks[] = [str_contains($files['migration'], $index), 'indice '.$index];
}

$failures = [];
foreach ($checks as [$ok, $description]) {
    if (!$ok) {
        $failures[] = $description;
    }
}
if ([] !== $failures) {
    fwrite(STDERR, "M9.2-G performance contract failed:\n- ".implode("\n- ", $failures)."\n");
    exit(1);
}

echo "M9.2-G performance contract passed.\n";
