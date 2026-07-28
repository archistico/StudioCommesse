<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'runtime' => $root.'/scripts/php-runtime-contract.php',
    'storage' => $root.'/scripts/attachment-storage-contract.php',
    'setupPs' => $root.'/scripts/setup.ps1',
    'setupSh' => $root.'/scripts/setup.sh',
    'validation' => $root.'/scripts/validate.ps1',
    'packager' => $root.'/scripts/package-release.ps1',
    'verifier' => $root.'/scripts/verify-release-package.ps1',
    'services' => $root.'/config/services.yaml',
    'readme' => $root.'/README.md',
    'attachments' => $root.'/docs/ATTACHMENTS.md',
];

$contents = [];
foreach ($files as $name => $path) {
    $content = file_get_contents($path);
    if (!is_string($content)) {
        fwrite(STDERR, 'File Hotfix 1 non leggibile: '.$name.PHP_EOL);
        exit(1);
    }
    $contents[$name] = $content;
}

$checks = [
    [str_contains($contents['runtime'], "'fileinfo'") && str_contains($contents['runtime'], 'php_ini_loaded_file()'), 'diagnostica fileinfo e php.ini'],
    [str_contains($contents['runtime'], 'extension=fileinfo') && str_contains($contents['runtime'], 'php_fileinfo.dll'), 'istruzioni Windows fileinfo'],
    [str_contains($contents['runtime'], "php -m | Select-String -Pattern '^fileinfo$'"), 'comando verifica Windows'],
    [str_contains($contents['storage'], "require_once __DIR__.'/php-runtime-contract.php'"), 'storage riusa il preflight'],
    [str_contains($contents['validation'], 'scripts/php-runtime-contract.php') && strpos($contents['validation'], 'scripts/php-runtime-contract.php') < strpos($contents['validation'], 'Invoke-Checked -Command "composer"'), 'preflight prima di Composer nel gate'],
    [str_contains($contents['setupPs'], 'scripts/php-runtime-contract.php'), 'setup PowerShell usa il preflight'],
    [str_contains($contents['setupSh'], 'scripts/php-runtime-contract.php'), 'setup shell usa il preflight'],
    [str_contains($contents['packager'], "'scripts/php-runtime-contract.php'") && str_contains($contents['verifier'], "'scripts/php-runtime-contract.php'"), 'runtime obbligatorio nel pacchetto'],
    [str_contains($contents['services'], "app.version: '0.9.2-M9.2-H'"), 'versione Hotfix 1'],
    [str_contains($contents['validation'], 'M9.2-H VALIDATION PASSED'), 'gate Hotfix 1'],
    [str_contains($contents['readme'], 'fileinfo') && str_contains($contents['attachments'], 'php --ini'), 'documentazione diagnostica'],
];

foreach ($checks as [$passed, $description]) {
    if (!$passed) {
        fwrite(STDERR, 'Contratto M9.2-C Hotfix 1 non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-C Hotfix 1 fileinfo superato.'.PHP_EOL;
