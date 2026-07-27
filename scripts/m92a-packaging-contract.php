<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$required = [
    'scripts/package-release.ps1',
    'docs/PACKAGING.md',
    'docs/PROJECT_HANDOFF.md',
    'docs/NEW_CHAT_START.md',
    'docs/VALIDATION_CHECKLIST.md',
];
foreach ($required as $relative) {
    if (!is_file($root.'/'.$relative)) {
        fwrite(STDERR, 'File M9.2-A mancante: '.$relative.PHP_EOL);
        exit(1);
    }
}

$services = (string) file_get_contents($root.'/config/services.yaml');
$package = json_decode((string) file_get_contents($root.'/package.json'), true, 32, JSON_THROW_ON_ERROR);
$packageLock = json_decode((string) file_get_contents($root.'/package-lock.json'), true, 64, JSON_THROW_ON_ERROR);
$validation = (string) file_get_contents($root.'/scripts/validate.ps1');
$packager = (string) file_get_contents($root.'/scripts/package-release.ps1');
$readme = (string) file_get_contents($root.'/README.md');
$packaging = (string) file_get_contents($root.'/docs/PACKAGING.md');
$gitignore = (string) file_get_contents($root.'/.gitignore');

$checks = [
    [str_contains($services, "app.version: '0.9.2-M9.2-A-HF1'"), 'versione applicativa'],
    [('0.9.2-m9.2-a-hf1' === ($package['version'] ?? null)), 'versione package.json'],
    [('0.9.2-m9.2-a-hf1' === ($packageLock['version'] ?? null)), 'versione package-lock root'],
    [('0.9.2-m9.2-a-hf1' === ($packageLock['packages']['']['version'] ?? null)), 'versione package-lock package'],
    [str_contains($validation, 'M9.2-A HOTFIX 1 VALIDATION PASSED'), 'gate M9.2-A'],
    [str_contains($validation, 'scripts/m92a-packaging-contract.php'), 'contratto nel gate'],
    [str_contains($validation, 'package-release.ps1'), 'smoke package nel gate'],
    [str_contains($packager, "'.env.local'"), 'esclusione .env.local'],
    [str_contains($packager, "'vendor'"), 'esclusione vendor'],
    [str_contains($packager, "'node_modules'"), 'esclusione node_modules'],
    [str_contains($packager, "'var'"), 'esclusione var'],
    [str_contains($packager, "'backups'"), 'esclusione backups'],
    [str_contains($packager, "'public/vendor/'"), 'esclusione asset generati'],
    [str_contains($packager, 'Assert-SafeArchiveEntryName'), 'controllo nomi ZIP'],
    [str_contains($packager, 'requiredEntries'), 'inventario minimo'],
    [str_contains($readme, 'StudioCommesse_M9.1_Hotfix3_Corretto.zip'), 'baseline corretta nel README'],
    [str_contains($packaging, 'non modifica i dati locali') || str_contains($packaging, 'non contiene dati'), 'confine dati/pacchetto'],
    [str_contains($gitignore, '/dist/'), 'dist ignorata'],
];
foreach ($checks as [$ok, $description]) {
    if (!$ok) {
        fwrite(STDERR, 'Contratto M9.2-A non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

if (!is_file($root.'/.env.local.dist')) {
    fwrite(STDERR, '.env.local.dist deve restare distribuibile.'.PHP_EOL);
    exit(1);
}

echo 'Contratto M9.2-A baseline e packaging superato.'.PHP_EOL;
