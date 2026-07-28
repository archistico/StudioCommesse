<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$matrix = require $root.'/config/authorization_matrix.php';
if (!is_array($matrix)) {
    fwrite(STDERR, "Matrice autorizzazioni non valida.\n");
    exit(1);
}

/** @var array<string, list<string>> $routeMethods */
$routeMethods = [];
foreach (glob($root.'/src/Controller/*.php') ?: [] as $controller) {
    $source = file_get_contents($controller);
    if (!is_string($source)) {
        fwrite(STDERR, 'Controller non leggibile: '.$controller.PHP_EOL);
        exit(1);
    }

    preg_match_all('/#\[Route\((.*?)\)\]/s', $source, $attributes);
    foreach ($attributes[1] as $attribute) {
        if (1 !== preg_match("/name:\s*'([^']+)'/", $attribute, $nameMatch)) {
            continue;
        }
        if (1 !== preg_match('/methods:\s*\[([^\]]+)\]/', $attribute, $methodsMatch)) {
            fwrite(STDERR, 'Metodi mancanti per la rotta '.$nameMatch[1].PHP_EOL);
            exit(1);
        }
        preg_match_all("/'([A-Z]+)'/", $methodsMatch[1], $methodMatches);
        $methods = array_values(array_unique($methodMatches[1]));
        sort($methods);
        $routeMethods[$nameMatch[1]] = $methods;
    }
}
ksort($routeMethods);
$matrixNames = array_keys($matrix);
sort($matrixNames);
if (array_keys($routeMethods) !== $matrixNames) {
    fwrite(STDERR, 'La matrice autorizzazioni non coincide con le rotte nominate.'.PHP_EOL);
    fwrite(STDERR, 'Mancanti: '.implode(', ', array_diff(array_keys($routeMethods), $matrixNames)).PHP_EOL);
    fwrite(STDERR, 'Inattese: '.implode(', ', array_diff($matrixNames, array_keys($routeMethods))).PHP_EOL);
    exit(1);
}

$allowedAccess = ['public', 'collaborator', 'partner', 'owner_or_partner', 'project_responsible_or_partner', 'activity_editor_or_partner', 'attachment_manager_or_partner'];
$allowedArchive = ['read', 'deny_write', 'not_applicable'];
foreach ($matrix as $route => $policy) {
    if (!is_array($policy)
        || !isset($policy['methods'], $policy['access'], $policy['ownership'], $policy['archive'], $policy['data'])
        || !is_array($policy['methods'])
        || [] === $policy['methods']
        || array_filter($policy['methods'], static fn (mixed $method): bool => !is_string($method) || '' === $method) !== []
        || !in_array($policy['access'], $allowedAccess, true)
        || !is_string($policy['ownership']) || '' === $policy['ownership']
        || !in_array($policy['archive'], $allowedArchive, true)
        || !is_string($policy['data']) || '' === $policy['data']
    ) {
        fwrite(STDERR, 'Politica incompleta o non valida per '.$route.PHP_EOL);
        exit(1);
    }

    $matrixMethods = $policy['methods'];
    sort($matrixMethods);
    if ($matrixMethods !== $routeMethods[$route]) {
        fwrite(STDERR, 'Metodi della matrice non coerenti per '.$route.PHP_EOL);
        exit(1);
    }
}

$files = [
    'activity' => file_get_contents($root.'/src/Controller/ActivityController.php'),
    'time' => file_get_contents($root.'/src/Controller/TimeEntryController.php'),
    'economics' => file_get_contents($root.'/src/Controller/EconomicsController.php'),
    'attachmentVoter' => file_get_contents($root.'/src/Security/Voter/AttachmentVoter.php'),
    'projectVoter' => file_get_contents($root.'/src/Security/Voter/ProjectVoter.php'),
    'expenseVoter' => file_get_contents($root.'/src/Security/Voter/ExpenseVoter.php'),
    'security' => file_get_contents($root.'/config/packages/security.yaml'),
    'permissions' => file_get_contents($root.'/docs/PERMISSIONS.md'),
    'authorizationDocs' => file_get_contents($root.'/docs/AUTHORIZATION_MATRIX.md'),
    'validation' => file_get_contents($root.'/scripts/validate.ps1'),
    'services' => file_get_contents($root.'/config/services.yaml'),
];
foreach ($files as $name => $content) {
    if (!is_string($content)) {
        fwrite(STDERR, 'File contratto non leggibile: '.$name.PHP_EOL);
        exit(1);
    }
}

$checks = [
    [str_contains($files['security'], 'path: ^/admin, roles: ROLE_PARTNER'), 'amministrazione utenti protetta'],
    [str_contains($files['activity'], 'le attività sono in sola lettura'), 'attività archiviate in sola lettura'],
    [str_contains($files['time'], 'le registrazioni ore sono in sola lettura'), 'ore archiviate in sola lettura'],
    [str_contains($files['attachmentVoter'], 'I documenti di una commessa archiviata sono in sola lettura.'), 'documenti archiviati in sola lettura'],
    [substr_count($files['economics'], 'Gli incassi di una commessa archiviata sono in sola lettura.') >= 3, 'incassi archiviati protetti in creazione modifica eliminazione'],
    [str_contains($files['projectVoter'], "public const VIEW_FINANCIAL = 'PROJECT_VIEW_FINANCIAL';"), 'voter finanziario commessa'],
    [str_contains($files['projectVoter'], 'if (self::VIEW_FINANCIAL === $attribute)') && str_contains($files['projectVoter'], 'Tariffe e costi sono riservati ai soci.'), 'dati finanziari negati ai responsabili collaboratori'],
    [str_contains($files['expenseVoter'], 'getRecordedBy()?->getId() === $userId'), 'spese proprie per collaboratore'],
    [str_contains($files['permissions'], 'La responsabilità della commessa non attribuisce una visibilità economica aggiuntiva'), 'permessi economici documentati'],
    [str_contains($files['authorizationDocs'], '48 rotte applicative'), 'matrice umana completa'],
    [str_contains($files['validation'], 'scripts/m92b-authorization-contract.php'), 'contratto M9.2-B nel gate'],
    [str_contains($files['validation'], 'M9.2-H VALIDATION PASSED'), 'gate M9.2-B'],
    [str_contains($files['services'], "app.version: '0.9.2-M9.2-H'"), 'versione M9.2-B'],
];
foreach ($checks as [$ok, $description]) {
    if (!$ok) {
        fwrite(STDERR, 'Contratto M9.2-B non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo sprintf('Contratto M9.2-B autorizzazioni superato: %d rotte coperte con metodi coerenti.', count($matrix)).PHP_EOL;
