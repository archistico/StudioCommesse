<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'databaseSubscriber' => $root.'/src/EventSubscriber/DatabaseExceptionSubscriber.php',
    'maintenanceMode' => $root.'/src/Service/MaintenanceMode.php',
    'monthlyReport' => $root.'/src/Service/MonthlyReportService.php',
    'services' => $root.'/config/services.yaml',
    'validation' => $root.'/scripts/validate.ps1',
    'phpstan' => $root.'/phpstan.neon',
];

$contents = [];
foreach ($paths as $name => $path) {
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fwrite(STDERR, 'File M9.2-C Hotfix 2 non leggibile: '.$name.PHP_EOL);
        exit(1);
    }
    $contents[$name] = str_replace("\r\n", "\n", $content);
}

$checks = [
    [!str_contains($contents['databaseSubscriber'], '$status = null;') && !str_contains($contents['databaseSubscriber'], '$template = null;'), 'narrowing esplicito subscriber database'],
    [1 === preg_match('/}\s*else\s*{\s*return;\s*}/s', $contents['databaseSubscriber']), 'ritorno esplicito per eccezioni non gestite'],
    [str_contains($contents['maintenanceMode'], "/** @phpstan-impure */\n    public function isEnabled(): bool"), 'isEnabled dichiarato impuro'],
    [str_contains($contents['monthlyReport'], 'AuditAction::TimeEntryCreated, AuditAction::TimeEntryUpdated, AuditAction::TimerStarted'), 'TimeEntryUpdated gestito nel report mensile'],
    [str_contains($contents['services'], "app.version: '0.9.2-M9.2-H'"), 'versione corrente dopo Hotfix 2'],
    [str_contains($contents['validation'], 'scripts/m92c-hotfix2-phpstan-contract.php'), 'contratto Hotfix 2 nel gate'],
    [str_contains($contents['validation'], 'M9.2-H VALIDATION PASSED'), 'gate corrente dopo Hotfix 2'],
    [str_contains($contents['phpstan'], 'level: 8'), 'PHPStan resta a livello 8'],
    [!str_contains($contents['phpstan'], 'treatPhpDocTypesAsCertain: false'), 'nessun indebolimento dei tipi PHPDoc'],
];

foreach ($checks as [$passed, $description]) {
    if (!$passed) {
        fwrite(STDERR, 'Contratto M9.2-C Hotfix 2 non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-C Hotfix 2 PHPStan superato.'.PHP_EOL;
