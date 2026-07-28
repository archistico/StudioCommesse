<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$hotfix2Contract = (string) file_get_contents($root.'/scripts/m92c-hotfix2-phpstan-contract.php');
$databaseSubscriber = str_replace("\r\n", "\n", (string) file_get_contents($root.'/src/EventSubscriber/DatabaseExceptionSubscriber.php'));
$validation = (string) file_get_contents($root.'/scripts/validate.ps1');
$services = (string) file_get_contents($root.'/config/services.yaml');

$checks = [
    [str_contains($hotfix2Contract, 'str_replace("\r\n", "\n", $content)'), 'normalizzazione CRLF/LF nel contratto Hotfix 2'],
    [str_contains($hotfix2Contract, "preg_match('/}\\s*else\\s*{\\s*return;\\s*}/s'"), 'controllo strutturale non dipendente dall’indentazione'],
    [1 === preg_match('/}\s*else\s*{\s*return;\s*}/s', $databaseSubscriber), 'subscriber con ritorno esplicito'],
    [str_contains($services, "app.version: '0.9.2-M9.2-H'"), 'versione Hotfix 3'],
    [str_contains($validation, 'scripts/m92c-hotfix3-contract.php'), 'contratto Hotfix 3 nel gate'],
    [str_contains($validation, 'M9.2-H VALIDATION PASSED'), 'gate Hotfix 3'],
];

foreach ($checks as [$passed, $description]) {
    if (!$passed) {
        fwrite(STDERR, 'Contratto M9.2-C Hotfix 3 non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-C Hotfix 3 superato.'.PHP_EOL;
