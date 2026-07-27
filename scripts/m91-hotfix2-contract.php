<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$testPath = $root.'/tests/Project/BackupContractTest.php';
if (!is_file($testPath)) {
    fwrite(STDERR, 'BackupContractTest mancante.'.PHP_EOL);
    exit(1);
}

$test = (string) file_get_contents($testPath);
$expected = "self::assertStringContainsString('readMigrationVersions(\$databasePath)', \$manager);";
$interpolated = 'self::assertStringContainsString("readMigrationVersions($databasePath)", $manager);';
$safetyBackupExpected = "self::assertStringContainsString('createUnlocked(\$safetyBackupDirectory)', \$manager);";
$safetyBackupInterpolated = 'self::assertStringContainsString("createUnlocked($safetyBackupDirectory)", $manager);';

if (!str_contains($test, $expected) || !str_contains($test, $safetyBackupExpected)) {
    fwrite(STDERR, 'I contratti del backup non usano stringhe PHP non interpolate.'.PHP_EOL);
    exit(1);
}
if (str_contains($test, $interpolated) || str_contains($test, $safetyBackupInterpolated)) {
    fwrite(STDERR, 'Un contratto del backup contiene ancora una stringa interpolata difettosa.'.PHP_EOL);
    exit(1);
}

$manager = (string) file_get_contents($root.'/src/Service/BackupManager.php');
if (!str_contains($manager, 'readMigrationVersions($databasePath)')) {
    fwrite(STDERR, 'La chiamata reale alle versioni di migrazione non è presente in BackupManager.'.PHP_EOL);
    exit(1);
}

echo 'Contratto M9.1 Hotfix 2 superato.'.PHP_EOL;
