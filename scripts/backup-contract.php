<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$requiredFiles = [
    'src/Service/BackupManager.php',
    'src/Service/AttachmentMutationLock.php',
    'src/Service/RequestRuntimeLock.php',
    'src/Service/MaintenanceMode.php',
    'src/EventSubscriber/MaintenanceModeSubscriber.php',
    'src/Command/CreateBackupCommand.php',
    'src/Command/VerifyBackupCommand.php',
    'src/Command/RestoreBackupCommand.php',
    'src/Command/EnableMaintenanceCommand.php',
    'src/Command/DisableMaintenanceCommand.php',
    'scripts/backup.ps1',
    'scripts/verify-backup.ps1',
    'scripts/restore-backup.ps1',
    'scripts/clear-maintenance.ps1',
    'docs/BACKUP_RESTORE.md',
];

foreach ($requiredFiles as $relativePath) {
    if (!is_file($root.'/'.$relativePath)) {
        fwrite(STDERR, 'File M9.1 mancante: '.$relativePath.PHP_EOL);
        exit(1);
    }
}

$manager = file_get_contents($root.'/src/Service/BackupManager.php');
$attachmentManager = file_get_contents($root.'/src/Service/AttachmentManager.php');
$restoreScript = file_get_contents($root.'/scripts/restore-backup.ps1');
$commonScript = file_get_contents($root.'/scripts/backup-common.ps1');
$services = file_get_contents($root.'/config/services.yaml');
if (!is_string($manager) || !is_string($attachmentManager) || !is_string($restoreScript)
    || !is_string($commonScript) || !is_string($services)
) {
    fwrite(STDERR, 'Impossibile leggere i contratti M9.1.'.PHP_EOL);
    exit(1);
}

$contracts = [
    [$manager, "studio-commesse-backup-v1", 'formato backup versionato'],
    [$manager, 'VACUUM INTO', 'snapshot SQLite coerente'],
    [$manager, 'PRAGMA database_list', 'rilevamento del file SQLite effettivamente connesso'],
    [$manager, 'PRAGMA integrity_check', 'verifica integrità SQLite'],
    [$manager, 'migrazioni dichiarate nel manifest', 'coerenza migrazioni manifest/database'],
    [$manager, 'createUnlocked($safetyBackupDirectory)', 'backup automatico pre-ripristino'],
    [$manager, '$completed && $ownsMaintenanceMode', 'manutenzione mantenuta sui fallimenti'],
    [$attachmentManager, 'AttachmentMutationLock', 'coordinamento allegati'],
    [$commonScript, 'Nome di file non sicuro nell\'archivio', 'nomi ZIP compatibili e sicuri su Windows'],
    [$commonScript, 'Nome di dispositivo Windows non consentito', 'rifiuto dispositivi speciali Windows'],
    [$restoreScript, '$Confirm -ne "RESTORE"', 'conferma esplicita ripristino'],
    [$services, "app.version: '0.9.2-M9.2-H'", 'versione applicativa corrente'],
];

foreach ($contracts as [$content, $needle, $description]) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, 'Contratto M9.1 assente: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto backup/ripristino M9.1 superato.'.PHP_EOL;
