<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$scriptPath = $root.'/scripts/package-release.ps1';
$validationPath = $root.'/scripts/validate.ps1';
$servicesPath = $root.'/config/services.yaml';

foreach ([$scriptPath, $validationPath, $servicesPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, 'File M9.2-A Hotfix 1 mancante: '.$path.PHP_EOL);
        exit(1);
    }
}

$script = (string) file_get_contents($scriptPath);
$validation = (string) file_get_contents($validationPath);
$services = (string) file_get_contents($servicesPath);

$checks = [
    [str_contains($script, 'foreach ($item in (Get-ChildItem -LiteralPath $Directory -Force)) {'), 'foreach PowerShell con comando racchiuso tra parentesi'],
    [!str_contains($script, 'foreach ($item in Get-ChildItem -LiteralPath $Directory -Force) {'), 'forma PowerShell non valida rimossa'],
    [str_contains($script, 'StudioCommesse_M9.2-A_Hotfix1_PowerShell_Parser.zip'), 'nome pacchetto Hotfix 1'],
    [str_contains($services, "app.version: '0.9.2-M9.2-A-HF1'"), 'versione applicativa Hotfix 1'],
    [str_contains($validation, 'M9.2-A HOTFIX 1 VALIDATION PASSED'), 'gate Hotfix 1'],
    [str_contains($validation, 'scripts/m92a-hotfix1-contract.php'), 'contratto Hotfix 1 incluso nella validazione'],
];

foreach ($checks as [$passed, $description]) {
    if (!$passed) {
        fwrite(STDERR, 'Contratto M9.2-A Hotfix 1 non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-A Hotfix 1 PowerShell superato.'.PHP_EOL;
