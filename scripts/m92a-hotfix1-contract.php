<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$scriptPath = $root.'/scripts/package-release.ps1';
if (!is_file($scriptPath)) {
    fwrite(STDERR, 'File M9.2-A Hotfix 1 mancante: '.$scriptPath.PHP_EOL);
    exit(1);
}

$script = (string) file_get_contents($scriptPath);
$checks = [
    [str_contains($script, 'foreach ($item in (Get-ChildItem -LiteralPath $Directory -Force)) {'), 'foreach PowerShell esplicito'],
    [str_contains($script, "IsNullOrWhiteSpace(\$EntryName) -or\n        \$EntryName.StartsWith('/') -or"), 'operatori -or collocati a fine riga'],
    [!str_contains($script, "IsNullOrWhiteSpace(\$EntryName)\n        -or"), 'operatori -or non collocati a inizio riga'],
];

foreach ($checks as [$passed, $description]) {
    if (!$passed) {
        fwrite(STDERR, 'Contratto storico M9.2-A Hotfix 1 non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto storico M9.2-A Hotfix 1 PowerShell superato.'.PHP_EOL;
