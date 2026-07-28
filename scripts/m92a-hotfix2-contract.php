<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$requiredFiles = [
    'scripts/package-release.ps1',
    'scripts/verify-release-package.ps1',
    'src/Kernel.php',
    'src/Controller/UserController.php',
    'src/Entity/User.php',
    'src/Repository/UserRepository.php',
    'src/Security/ActiveUserChecker.php',
    'src/Service/AttachmentManager.php',
    'templates/layout/app.html.twig',
    'templates/user/index.html.twig',
    'tests/Controller/AttachmentManagementTest.php',
    'docs/PERMISSIONS.md',
];
foreach ($requiredFiles as $relative) {
    $path = $root.'/'.$relative;
    if (!is_file($path) || 0 === filesize($path)) {
        fwrite(STDERR, 'File critico M9.2-A Hotfix 2 mancante o vuoto: '.$relative.PHP_EOL);
        exit(1);
    }
}

$packager = (string) file_get_contents($root.'/scripts/package-release.ps1');
$verifier = (string) file_get_contents($root.'/scripts/verify-release-package.ps1');
$validation = (string) file_get_contents($root.'/scripts/validate.ps1');
$services = (string) file_get_contents($root.'/config/services.yaml');
$control = (string) file_get_contents($root.'/src/Controller/ControlController.php');
$permissions = (string) file_get_contents($root.'/docs/PERMISSIONS.md');
$attachmentManager = (string) file_get_contents($root.'/src/Service/AttachmentManager.php');
$userController = (string) file_get_contents($root.'/src/Controller/UserController.php');

$checks = [
    [str_contains($services, "app.version: '0.9.2-M9.2-H'"), 'versione applicativa Hotfix 2'],
    [str_contains($validation, 'M9.2-H VALIDATION PASSED'), 'gate Hotfix 2'],
    [str_contains($validation, 'verify-release-package.ps1'), 'verifica ZIP indipendente nel gate'],
    [str_contains($packager, 'src/Kernel.php'), 'Kernel obbligatorio nel packaging'],
    [str_contains($packager, 'src/Controller/UserController.php'), 'controller utenti obbligatorio nel packaging'],
    [str_contains($packager, 'file critico vuoto'), 'rifiuto file critici vuoti'],
    [str_contains($verifier, 'Inventario pacchetto diverso dal sorgente distribuibile'), 'confronto inventario sorgente/ZIP'],
    [str_contains($verifier, "Pattern = '^src/Entity/.+\\.php$'"), 'famiglia entità obbligatoria'],
    [str_contains($verifier, "Pattern = '^templates/.+\\.twig$'"), 'famiglia template obbligatoria'],
    [str_contains($verifier, "Pattern = '^tests/.+\\.php$'"), 'famiglia test obbligatoria'],
    [str_contains($control, 'array_key_exists($key, $queryValues)'), 'filtri riconosciuti rilevati esplicitamente'],
    [!str_contains($control, '$request->query->count() > 0'), 'reset filtri su parametro estraneo rimosso'],
    [str_contains($permissions, 'Vedere le proprie spese'), 'permessi economici collaboratore documentati'],
    [str_contains($permissions, 'Consultare o gestire incassi'), 'incassi separati dalle spese'],
    [str_contains($attachmentManager, 'Non è possibile aggiungere documenti a una commessa archiviata.'), 'invariante upload archiviato nel servizio'],
    [str_contains($userController, "#[Route('/admin/utenti')]"), 'gestione utenti e rotta admin presenti'],
];
foreach ($checks as [$passed, $description]) {
    if (!$passed) {
        fwrite(STDERR, 'Contratto M9.2-A Hotfix 2 non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-A Hotfix 2 completezza e filtri superato.'.PHP_EOL;
