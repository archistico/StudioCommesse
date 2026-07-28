<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'services' => 'config/services.yaml',
    'monolog' => 'config/packages/monolog.yaml',
    'matrix' => 'config/authorization_matrix.php',
    'security' => 'config/packages/security.yaml',
    'controller' => 'src/Controller/AuditController.php',
    'repository' => 'src/Repository/AuditLogRepository.php',
    'entity' => 'src/Entity/AuditLog.php',
    'logger' => 'src/Service/AuditLogger.php',
    'http' => 'src/EventSubscriber/HttpExceptionSubscriber.php',
    'database' => 'src/EventSubscriber/DatabaseExceptionSubscriber.php',
    'template' => 'templates/audit/index.html.twig',
    'layout' => 'templates/layout/app.html.twig',
    'readme' => 'README.md',
    'docs' => 'docs/OPERATIONAL_AUDIT.md',
    'authorizationDocs' => 'docs/AUTHORIZATION_MATRIX.md',
    'validation' => 'scripts/validate.ps1',
    'packager' => 'scripts/package-release.ps1',
    'verifier' => 'scripts/verify-release-package.ps1',
];

$contents = [];
foreach ($paths as $name => $relativePath) {
    $value = file_get_contents($root.'/'.$relativePath);
    if (false === $value) {
        fwrite(STDERR, 'File M9.2-D non leggibile: '.$relativePath.PHP_EOL);
        exit(1);
    }
    $contents[$name] = str_replace("\r\n", "\n", $value);
}

/** @var array<string, mixed> $matrix */
$matrix = require $root.'/config/authorization_matrix.php';
$checks = [
    [str_contains($contents['services'], "app.version: '0.9.2-M9.2-H'"), 'versione M9.2-D'],
    [str_contains($contents['validation'], 'scripts/m92d-operational-audit-contract.php'), 'contratto M9.2-D nel gate'],
    [str_contains($contents['validation'], 'M9.2-H VALIDATION PASSED'), 'gate M9.2-D'],
    [str_contains($contents['controller'], "#[Route('/audit')]"), 'prefisso rotte audit'],
    [str_contains($contents['controller'], "#[IsGranted('ROLE_PARTNER')]"), 'accesso audit riservato ai Soci'],
    [str_contains($contents['controller'], "name: 'app_audit_csv'"), 'esportazione CSV audit'],
    [str_contains($contents['repository'], 'public function findPage(AuditSearchCriteria $criteria): AuditPage'), 'paginazione server-side'],
    [str_contains($contents['repository'], 'public function findForExport(AuditSearchCriteria $criteria'), 'query export'],
    [str_contains($contents['repository'], 'audit.details LIKE :request_id'), 'filtro request ID'],
    [str_contains($contents['entity'], 'public function getVisibleDetails(): array'), 'separazione dettagli tecnici'],
    [str_contains($contents['logger'], 'RequestIdSubscriber::ATTRIBUTE'), 'correlazione request ID'],
    [str_contains($contents['logger'], "details['route']"), 'correlazione rotta'],
    [str_contains($contents['logger'], "details['method']"), 'correlazione metodo'],
    [str_contains($contents['monolog'], '- operations'), 'canale operations'],
    [substr_count($contents['monolog'], 'formatter: monolog.formatter.json') >= 4, 'formatter JSON per audit e operations'],
    [str_contains($contents['http'], "monolog.logger.operations"), 'errori HTTP nel canale operations'],
    [str_contains($contents['database'], "monolog.logger.operations"), 'errori database nel canale operations'],
    [str_contains($contents['template'], 'Audit operativo'), 'pagina audit'],
    [str_contains($contents['template'], 'Applica filtri'), 'filtri audit'],
    [str_contains($contents['template'], 'Esporta CSV'), 'comando export'],
    [!preg_match('/\bcol-(?:sm|md)-\d+\b/', $contents['template']), 'breakpoint da lg'],
    [str_contains($contents['layout'], "path('app_audit_index')"), 'voce Audit nella navigazione Soci'],
    [str_contains($contents['security'], "path: '^/audit(?:/|$)'") && str_contains($contents['security'], 'roles: ROLE_PARTNER'), 'regola access_control audit valida e riservata ai Soci'],
    [isset($matrix['app_audit_index'], $matrix['app_audit_csv']), 'rotte audit nella matrice'],
    [48 === count($matrix), '48 rotte autorizzate'],
    [str_contains($contents['authorizationDocs'], '48 rotte applicative'), 'documentazione matrice aggiornata'],
    [str_contains($contents['docs'], 'security-audit.log') && str_contains($contents['docs'], 'operations.log'), 'documentazione log'],
    [str_contains($contents['readme'], 'registro audit filtrabile riservato ai Soci'), 'README sintetico aggiornato'],
    [!preg_match('/\bM\d+(?:\.\d+)*(?:-[A-Z0-9.]+)?\b|baseline|candidate|VALIDATION PASSED/i', $contents['readme']), 'README senza cronologia milestone'],
    [str_contains($contents['packager'], "'src/Controller/AuditController.php'"), 'controller audit obbligatorio nel package'],
    [str_contains($contents['verifier'], "'tests/Controller/OperationalAuditTest.php'"), 'test audit obbligatorio nel package'],
];

foreach ($checks as [$ok, $description]) {
    if (!$ok) {
        fwrite(STDERR, 'Contratto M9.2-D non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-D audit operativo e logging superato.'.PHP_EOL;
