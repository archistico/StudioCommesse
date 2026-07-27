<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'src/Service/BackupManager.php',
    'src/Service/FileLockManager.php',
    'src/Controller/TimeEntryController.php',
    'src/Service/ProjectFinancialService.php',
    'templates/economics/index.html.twig',
    'scripts/verify-backup.ps1',
    'scripts/backup-common.ps1',
];
foreach ($files as $file) {
    if (!is_file($root.'/'.$file)) {
        fwrite(STDERR, 'File M9.1 Hotfix 1 mancante: '.$file.PHP_EOL);
        exit(1);
    }
}
$backup = (string) file_get_contents($root.'/src/Service/BackupManager.php');
$lock = (string) file_get_contents($root.'/src/Service/FileLockManager.php');
$time = (string) file_get_contents($root.'/src/Controller/TimeEntryController.php');
$financial = (string) file_get_contents($root.'/src/Service/ProjectFinancialService.php');
$template = (string) file_get_contents($root.'/templates/economics/index.html.twig');
$verify = (string) file_get_contents($root.'/scripts/verify-backup.ps1');
$common = (string) file_get_contents($root.'/scripts/backup-common.ps1');
$checks = [
    [$backup, 'if (!$item instanceof \SplFileInfo)', 'narrowing SplFileInfo'],
    [$lock, '@param int<0, 7> $operation', 'tipo operazione flock'],
    [$time, 'positiveInt($request->query->get(\'project\'))', 'filtro progetto vuoto'],
    [$time, 'positiveInt($request->query->get(\'activity\'))', 'filtro attività vuoto'],
    [$time, 'positiveInt($request->query->get(\'user\'))', 'filtro utente vuoto'],
    [$financial, 'summarizeByClient', 'riepilogo clienti'],
    [$template, 'Importi dovuti per cliente', 'tabella importi dovuti'],
    [$verify, 'Resolve-StudioBackupArchive -ArchivePath $Archive', 'risoluzione archivio verifica'],
    [$common, 'Archivi disponibili:', 'diagnostica backup inesistente'],
];
foreach ($checks as [$content, $needle, $description]) {
    if (!str_contains($content, $needle)) {
        fwrite(STDERR, 'Contratto M9.1 Hotfix 1 assente: '.$description.PHP_EOL);
        exit(1);
    }
}
if (str_contains($time, "query->getInt('project')") || str_contains($time, "query->getInt('activity')") || str_contains($time, "query->getInt('user')")) {
    fwrite(STDERR, 'Il report Ore usa ancora conversioni rigide dei filtri opzionali.'.PHP_EOL);
    exit(1);
}
echo 'Contratto M9.1 Hotfix 1 superato.'.PHP_EOL;
