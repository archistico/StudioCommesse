<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'services' => $root.'/config/services.yaml',
    'validation' => $root.'/scripts/validate.ps1',
    'controller' => $root.'/src/Controller/DashboardController.php',
    'activityRepository' => $root.'/src/Repository/ActivityRepository.php',
    'timeRepository' => $root.'/src/Repository/TimeEntryRepository.php',
    'dashboardRepository' => $root.'/src/Repository/DashboardRepository.php',
    'dashboard' => $root.'/templates/dashboard/index.html.twig',
    'activities' => $root.'/templates/activity/index.html.twig',
    'projects' => $root.'/templates/project/index.html.twig',
];

$contents = [];
foreach ($files as $name => $path) {
    $value = file_get_contents($path);
    if (false === $value) {
        fwrite(STDERR, 'File M9.2-C Hotfix 4 non leggibile: '.$name.PHP_EOL);
        exit(1);
    }
    $contents[$name] = str_replace("\r\n", "\n", $value);
}

$checks = [
    [str_contains($contents['services'], "app.version: '0.9.2-M9.2-H'"), 'versione Hotfix 4'],
    [str_contains($contents['validation'], 'scripts/m92c-hotfix4-dashboard-contract.php'), 'contratto Hotfix 4 nel gate'],
    [str_contains($contents['validation'], 'M9.2-H VALIDATION PASSED'), 'gate Hotfix 4'],
    [str_contains($contents['controller'], "'recent_activities' => \$activityRepository->findRecentlyUpdated()"), 'attività recenti nel controller'],
    [str_contains($contents['controller'], "'recent_time_entries' => \$timeEntryRepository->findRecentlyUpdated()"), 'ore recenti nel controller'],
    [str_contains($contents['controller'], "\$dashboardRepository->summarize(\$currentMonth, \$currentMonth->modify('+1 month'))"), 'riepilogo dashboard consolidato del mese corrente'],
    [str_contains($contents['controller'], "'workedMinutes' => \$summary->workedMinutes"), 'totale ore effettuate dal riepilogo consolidato'],
    [str_contains($contents['dashboardRepository'], 'time_entry.started_at >= :month_from'), 'inizio mese nella query dashboard'],
    [str_contains($contents['dashboardRepository'], 'time_entry.started_at < :month_before'), 'fine esclusiva mese nella query dashboard'],
    [!str_contains($contents['controller'], "'plannedMinutes'"), 'ore pianificate rimosse dal controller'],
    [str_contains($contents['activityRepository'], 'public function findRecentlyUpdated(int $limit = 8): array'), 'query attività recenti'],
    [!str_contains($contents['activityRepository'], 'sumPlannedMinutesForOpenActivities'), 'aggregato ore pianificate rimosso'],
    [str_contains($contents['timeRepository'], 'public function findRecentlyUpdated(int $limit = 8): array'), 'query ore recenti'],
    [str_contains($contents['timeRepository'], 'public function sumCompletedMinutesForPeriod(DateTimeImmutable $startedFrom, DateTimeImmutable $startedBefore): int'), 'aggregato ore effettuate per periodo'],
    [str_contains($contents['dashboard'], 'data-dashboard-operational-summary'), 'seconda riga operativa'],
    [str_contains($contents['dashboard'], 'Commesse aggiornate di recente'), 'tabella commesse recenti'],
    [str_contains($contents['dashboard'], 'Attività aggiornate di recente'), 'tabella attività recenti'],
    [str_contains($contents['dashboard'], 'Ore aggiornate di recente'), 'tabella ore recenti'],
    [str_contains($contents['dashboard'], '<th scope="col">Descrizione</th>'), 'descrizione nelle ore recenti'],
    [str_contains($contents['dashboard'], 'Commesse in attesa'), 'etichetta commesse in attesa'],
    [str_contains($contents['dashboard'], 'Commesse in ritardo'), 'etichetta commesse in ritardo'],
    [!str_contains($contents['dashboard'], 'Ore pianificate'), 'card ore pianificate rimossa'],
    [!preg_match('/\bcol-(?:sm|md)-\d+\b/', $contents['dashboard']), 'breakpoint responsive da lg'],
    [!str_contains($contents['dashboard'], 'Quadro operativo'), 'rimozione vecchio riquadro laterale'],
    [str_contains($contents['activities'], 'onchange="this.form.requestSubmit()"'), 'filtro assegnatario automatico'],
    [str_contains($contents['activities'], 'mostrate subito le tue attività'), 'comportamento predefinito documentato'],
    [str_contains($contents['projects'], 'data-priority="{{ project.priority.value }}"'), 'priorità iconiche identificabili'],
    [str_contains($contents['projects'], 'aria-label="Priorità {{ project.priority.label }}"'), 'icone priorità accessibili'],
];

foreach ($checks as [$ok, $description]) {
    if (!$ok) {
        fwrite(STDERR, 'Contratto M9.2-C Hotfix 4 non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-C Hotfix 4 dashboard e viste operative superato.'.PHP_EOL;
