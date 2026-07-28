<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'services' => $root.'/config/services.yaml',
    'packageJson' => $root.'/package.json',
    'setup' => $root.'/scripts/setup.ps1',
    'preflight' => $root.'/scripts/release-preflight.ps1',
    'update' => $root.'/scripts/update.ps1',
    'installSmoke' => $root.'/scripts/install-smoke.ps1',
    'packager' => $root.'/scripts/package-release.ps1',
    'verifier' => $root.'/scripts/verify-release-package.ps1',
    'validation' => $root.'/scripts/validate.ps1',
    'readme' => $root.'/README.md',
    'deploymentDocs' => $root.'/docs/INSTALL_UPDATE.md',
    'apacheDocs' => $root.'/docs/APACHE.md',
    'composer' => $root.'/composer.json',
    'htaccess' => $root.'/public/.htaccess',
];

$contents = [];
foreach ($paths as $name => $path) {
    $content = @file_get_contents($path);
    if (!is_string($content) || '' === $content) {
        fwrite(STDERR, 'File M9.2-F non leggibile: '.$name.PHP_EOL);
        exit(1);
    }
    $contents[$name] = $content;
}

$package = json_decode($contents['packageJson'], true, 512, JSON_THROW_ON_ERROR);

foreach (glob($root.'/scripts/*.ps1') ?: [] as $powerShellPath) {
    $powerShell = (string) file_get_contents($powerShellPath);
    if (1 === preg_match('/[\x{2018}\x{2019}\x{201C}\x{201D}]/u', $powerShell)) {
        fwrite(STDERR, 'Contratto M9.2-F non rispettato: virgolette tipografiche in '.basename($powerShellPath).PHP_EOL);
        exit(1);
    }
}
$checks = [
    [str_contains($contents['services'], "app.version: '0.9.2-M9.2-H'"), 'versione M9.2-F'],
    [('0.9.2-m9.2-h' === ($package['version'] ?? null)), 'versione asset M9.2-F'],
    [str_contains($contents['validation'], 'scripts/m92f-deployment-contract.php'), 'contratto M9.2-F nel gate'],
    [str_contains($contents['validation'], 'M9.2-H VALIDATION PASSED'), 'gate M9.2-F'],
    [str_contains($contents['setup'], "release-preflight.ps1') -Mode Install"), 'preflight nel setup'],
    [str_contains($contents['preflight'], "[ValidateSet('Install', 'Update', 'Package')]"), 'modalità preflight'],
    [str_contains($contents['preflight'], "'var/studio_commesse.db'"), 'database richiesto per update'],
    [str_contains($contents['preflight'], "'public/vendor'"), 'asset generati vietati nel package'],
    [str_contains($contents['update'], "if (\$Confirm -ne 'UPDATE')"), 'conferma esplicita update'],
    [str_contains($contents['update'], 'Invoke-SelfStagedUpdate'), 'staging automatico update'],
    [str_contains($contents['update'], "scripts/backup.ps1') -DestinationDirectory"), 'backup dati pre-update'],
    [str_contains($contents['update'], "scripts/verify-backup.ps1') -Archive"), 'verifica backup pre-update'],
    [str_contains($contents['update'], "scripts/package-release.ps1') -OutputPath \$previousCodeArchive"), 'snapshot codice precedente'],
    [str_contains($contents['update'], "app:maintenance:enable"), 'manutenzione prima update'],
    [str_contains($contents['update'], 'Remove-DeployableFiles'), 'rimozione file distribuibili obsoleti'],
    [str_contains($contents['update'], 'Restore-CodeArchive'), 'rollback codice'],
    [str_contains($contents['update'], 'app:backup:restore'), 'rollback dati'],
    [str_contains($contents['update'], 'La modalità manutenzione resta attiva'), 'protezione su rollback fallito'],
    [str_contains($contents['installSmoke'], 'ExtractToDirectory'), 'estrazione smoke pulita'],
    [str_contains($contents['installSmoke'], '-Mode Package'), 'preflight package nello smoke'],
    [str_contains($contents['packager'], 'scripts/update.ps1'), 'update incluso nel package'],
    [str_contains($contents['verifier'], 'docs/INSTALL_UPDATE.md'), 'documentazione deployment obbligatoria'],
    [str_contains($contents['packager'], 'StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip'), 'nome package M9.2-F'],
    [str_contains($contents['readme'], 'update.ps1'), 'update sintetico nel README'],
    [str_contains($contents['deploymentDocs'], 'backups/update-YYYYMMDD-HHMMSS/'), 'directory rollback documentata'],
    [str_contains($contents['composer'], 'symfony/apache-pack'), 'dipendenza Apache'],
    [str_contains($contents['htaccess'], 'RewriteEngine On'), 'regole Apache'],
    [str_contains($contents['apacheDocs'], 'AllowOverride All'), 'configurazione Apache documentata'],
    [!str_contains($contents['readme'], 'M9.2-F'), 'README senza milestone'],
];

foreach ($checks as [$passed, $description]) {
    if (!$passed) {
        fwrite(STDERR, 'Contratto M9.2-F non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-F installazione e aggiornamento superato.'.PHP_EOL;
