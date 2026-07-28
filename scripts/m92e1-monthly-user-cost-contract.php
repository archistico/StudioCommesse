<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'controller' => $root.'/src/Controller/MonthlyReportController.php',
    'repository' => $root.'/src/Repository/MonthlyReportRepository.php',
    'service' => $root.'/src/Service/MonthlyReportService.php',
    'dto' => $root.'/src/Query/MonthlyUserCostReportRow.php',
    'template' => $root.'/templates/report/monthly.html.twig',
    'test' => $root.'/tests/Controller/MonthlyReportTest.php',
    'contract_test' => $root.'/tests/Project/MonthlyUserCostReportContractTest.php',
    'docs' => $root.'/docs/MONTHLY_REPORT.md',
    'matrix' => $root.'/config/authorization_matrix.php',
    'services' => $root.'/config/services.yaml',
    'validation' => $root.'/scripts/validate.ps1',
    'packager' => $root.'/scripts/package-release.ps1',
    'verifier' => $root.'/scripts/verify-release-package.ps1',
];
$contents = [];
foreach ($paths as $label => $path) {
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Contratto M9.2-E.1: file %s mancante.', $label));
    }
    $contents[$label] = (string) file_get_contents($path);
}

$checks = [
    [str_contains($contents['controller'], "name: 'app_monthly_report_users_csv'"), 'CSV riepilogo utenti separato'],
    [str_contains($contents['repository'], 'findUserCostSummaries'), 'query aggregata per utente'],
    [str_contains($contents['repository'], 'time_entry.ended_at IS NOT NULL'), 'timer aperti esclusi'],
    [str_contains($contents['repository'], 'worker.default_hourly_rate_cents'), 'tariffa standard corrente'],
    [str_contains($contents['repository'], 'SUM(time_entry.cost_snapshot_cents)'), 'costo storico snapshot'],
    [str_contains($contents['service'], '$workedMinutes * $standardHourlyRateCents / 60'), 'costo teorico su totale minuti'],
    [str_contains($contents['template'], 'Riepilogo ore e costi per utente'), 'tabella mensile utenti'],
    [str_contains($contents['template'], 'Non impostata'), 'tariffa zero non presentata come costo nullo'],
    [str_contains($contents['test'], 'testMonthlyUserSummaryCsvIsSeparateAndPartnerOnly'), 'test CSV e permessi'],
    [str_contains($contents['docs'], 'Costo standard teorico'), 'regole documentate'],
    [str_contains($contents['matrix'], "'app_monthly_report_users_csv'"), 'rotta nella matrice autorizzazioni'],
    [str_contains($contents['services'], "app.version: '0.9.2-M9.2-H'"), 'versione M9.2-E.1'],
    [str_contains($contents['validation'], 'scripts/m92e1-monthly-user-cost-contract.php'), 'contratto incluso nel gate'],
    [str_contains($contents['validation'], 'M9.2-H VALIDATION PASSED'), 'gate M9.2-E.1'],
    [str_contains($contents['packager'], 'StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip'), 'nome package M9.2-E.1'],
    [str_contains($contents['packager'], "'src/Query/MonthlyUserCostReportRow.php'"), 'DTO obbligatorio nel package'],
    [str_contains($contents['packager'], "'tests/Project/MonthlyUserCostReportContractTest.php'"), 'test contratto obbligatorio nel package'],
    [str_contains($contents['verifier'], "'scripts/m92e1-monthly-user-cost-contract.php'"), 'script verificato nel package'],
    [str_contains($contents['verifier'], "'docs/MONTHLY_REPORT.md'"), 'documentazione verificata nel package'],
];
foreach ($checks as [$ok, $description]) {
    if (!$ok) {
        throw new RuntimeException('Contratto M9.2-E.1 non rispettato: '.$description);
    }
}

echo "M9.2-E.1 monthly user cost contract passed.
";
