<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'services' => $root.'/config/services.yaml',
    'validation' => $root.'/scripts/validate.ps1',
    'controller' => $root.'/src/Controller/DashboardController.php',
    'repository' => $root.'/src/Repository/DashboardRepository.php',
    'dashboard' => $root.'/templates/dashboard/index.html.twig',
    'test' => $root.'/tests/Controller/DashboardUiHotfixTest.php',
    'packager' => $root.'/scripts/package-release.ps1',
];

$contents = [];
foreach ($files as $name => $path) {
    $value = file_get_contents($path);
    if (false === $value) {
        fwrite(STDERR, 'File M9.2-E.2 non leggibile: '.$name.PHP_EOL);
        exit(1);
    }
    $contents[$name] = str_replace("\r\n", "\n", $value);
}

$checks = [
    [str_contains($contents['services'], "app.version: '0.9.2-M9.2-H'"), 'versione M9.2-E.2'],
    [str_contains($contents['validation'], 'scripts/m92e2-dashboard-current-month-contract.php'), 'contratto M9.2-E.2 nel gate'],
    [str_contains($contents['validation'], 'M9.2-H VALIDATION PASSED'), 'gate M9.2-E.2'],
    [str_contains($contents['controller'], "new DateTimeImmutable('first day of this month midnight')"), 'inizio mese corrente'],
    [str_contains($contents['controller'], "\$currentMonth->modify('+1 month')"), 'fine esclusiva mese corrente'],
    [str_contains($contents['controller'], "\$dashboardRepository->summarize(\$currentMonth, \$currentMonth->modify('+1 month'))"), 'aggregazione dashboard limitata al mese'],
    [!str_contains($contents['controller'], "'plannedMinutes'"), 'rimozione ore pianificate dal controller'],
    [str_contains($contents['repository'], 'public function summarize(DateTimeImmutable $currentMonth, DateTimeImmutable $nextMonth'), 'metodo riepilogo dashboard'],
    [str_contains($contents['repository'], 'time_entry.started_at >= :month_from'), 'limite iniziale incluso'],
    [str_contains($contents['repository'], 'time_entry.started_at < :month_before'), 'limite finale escluso'],
    [str_contains($contents['repository'], 'time_entry.ended_at IS NOT NULL'), 'timer aperti esclusi'],
    [str_contains($contents['dashboard'], 'Commesse in attesa'), 'etichetta commesse in attesa'],
    [str_contains($contents['dashboard'], 'Commesse in ritardo'), 'etichetta commesse in ritardo'],
    [str_contains($contents['dashboard'], 'data-worked-minutes="{{ project_statistics.workedMinutes }}"'), 'totale ore identificabile'],
    [str_contains($contents['dashboard'], '<span class="fs-4">Ore effettuate</span>'), 'ore e titolo sulla stessa riga'],
    [str_contains($contents['dashboard'], '<div class="text-secondary">Registrazioni concluse</div>'), 'seconda riga descrittiva'],
    [!str_contains($contents['dashboard'], 'Ore pianificate'), 'card ore pianificate rimossa'],
    [!str_contains($contents['dashboard'], 'data-planned-minutes'), 'dato ore pianificate rimosso'],
    [str_contains($contents['test'], "new DateTimeImmutable('first day of this month midnight')"), 'test indipendente dal mese di esecuzione'],
    [str_contains($contents['test'], 'Voce mese precedente esclusa'), 'regressione mese precedente'],
    [str_contains($contents['test'], 'assertSelectorExists(\'[data-worked-minutes="90"]\')'), 'totale mensile verificato'],
    [str_contains($contents['packager'], 'StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip'), 'nome package M9.2-E.2'],
];

foreach ($checks as [$ok, $description]) {
    if (!$ok) {
        fwrite(STDERR, 'Contratto M9.2-E.2 non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-E.2 dashboard mensile superato.'.PHP_EOL;
