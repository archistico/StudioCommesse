<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$composer = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
$lock = json_decode((string) file_get_contents($root.'/composer.lock'), true, 512, JSON_THROW_ON_ERROR);
$htaccess = (string) file_get_contents($root.'/public/.htaccess');
$update = (string) file_get_contents($root.'/scripts/update.ps1');
$validation = (string) file_get_contents($root.'/scripts/validate.ps1');
$services = (string) file_get_contents($root.'/config/services.yaml');
$package = json_decode((string) file_get_contents($root.'/package.json'), true, 512, JSON_THROW_ON_ERROR);

$locked = [];
foreach ($lock['packages'] ?? [] as $lockedPackage) {
    $locked[$lockedPackage['name'] ?? ''] = $lockedPackage['version'] ?? null;
}

$checks = [
    [(($composer['require']['symfony/apache-pack'] ?? null) === '^1.0'), 'dipendenza Composer Apache'],
    [(($locked['symfony/apache-pack'] ?? null) === 'v1.0.1'), 'lock Apache Pack'],
    [str_contains($htaccess, 'DirectoryIndex index.php'), 'front controller Apache'],
    [str_contains($htaccess, 'RewriteEngine On'), 'mod_rewrite Apache'],
    [str_contains($htaccess, 'RewriteRule ^ %{ENV:BASE}/index.php [L]'), 'fallback al front controller'],
    [str_contains($update, 'Invoke-SelfStagedUpdate'), 'staging automatico update'],
    [str_contains($update, "'studio-commesse-update-release-'"), 'directory temporanea staging'],
    [str_contains($update, '-StagedRelease'), 'protezione ricorsione staging'],
    [str_contains($update, '[AllowEmptyCollection()]'), 'collezione file obsoleti vuota ammessa'],
    [str_contains($update, 'if ($staleEntries.Count -gt 0)'), 'rimozione file obsoleti solo se presenti'],
    [str_contains($update, 'if ($null -eq $RelativePaths -or $RelativePaths.Count -eq 0)'), 'no-op difensivo su lista vuota'],
    [str_contains($services, "app.version: '0.9.2-M9.2-H'"), 'versione M9.2-F.1'],
    [(($package['version'] ?? null) === '0.9.2-m9.2-h'), 'versione asset M9.2-F.1'],
    [str_contains($validation, 'scripts/m92f1-apache-update-contract.php'), 'contratto nel gate'],
    [str_contains($validation, 'M9.2-H VALIDATION PASSED'), 'gate M9.2-F.1'],
];

foreach ($checks as [$passed, $description]) {
    if (!$passed) {
        fwrite(STDERR, 'Contratto M9.2-F.1 non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-F.1 Apache e staging update superato.'.PHP_EOL;
