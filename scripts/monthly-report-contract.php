<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'controller' => $root.'/src/Controller/MonthlyReportController.php',
    'repository' => $root.'/src/Repository/MonthlyReportRepository.php',
    'service' => $root.'/src/Service/MonthlyReportService.php',
    'template' => $root.'/templates/report/monthly.html.twig',
    'economics' => $root.'/src/Controller/EconomicsController.php',
    'expense_voter' => $root.'/src/Security/Voter/ExpenseVoter.php',
];
foreach ($files as $label => $path) {
    if (!is_file($path)) {
        throw new RuntimeException(sprintf('Contratto M8: file %s mancante.', $label));
    }
}
$controller = (string) file_get_contents($files['controller']);
$repository = (string) file_get_contents($files['repository']);
$template = (string) file_get_contents($files['template']);
$economics = (string) file_get_contents($files['economics']);
$voter = (string) file_get_contents($files['expense_voter']);

$expectations = [
    [$controller, "#[Route('/report/mensile')]"],
    [$controller, "#[IsGranted('ROLE_PARTNER')]"],
    [$controller, "name: 'app_monthly_report_csv'"],
    [$repository, 'findProjectMetrics'],
    [$repository, 'findTimeEntries'],
    [$repository, 'findActionCounts'],
    [$template, 'Andamento per commessa'],
    [$template, 'Registrazioni ore del mese'],
    [$template, 'Totali per azione'],
    [$template, 'Cronologia delle azioni'],
    [$economics, 'findForProjectAndRecorder'],
    [$economics, "'payments' => \$isPartner ? \$paymentRepository->findForProject(\$project) : []"],
    [$voter, "public const MANAGE = 'EXPENSE_MANAGE'"],
];
foreach ($expectations as [$contents, $needle]) {
    if (!str_contains($contents, $needle)) {
        throw new RuntimeException('Contratto M8 non rispettato: '.$needle);
    }
}

echo "M8 monthly report contract passed.\n";
