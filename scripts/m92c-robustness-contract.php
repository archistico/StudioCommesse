<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
    'transaction' => file_get_contents($root.'/src/Service/AuditedTransaction.php'),
    'audit' => file_get_contents($root.'/src/Service/AuditLogger.php'),
    'timer' => file_get_contents($root.'/src/Service/TimerService.php'),
    'timerLock' => file_get_contents($root.'/src/Service/TimerMutationLock.php'),
    'attachmentLock' => file_get_contents($root.'/src/Service/AttachmentMutationLock.php'),
    'timeController' => file_get_contents($root.'/src/Controller/TimeEntryController.php'),
    'storage' => file_get_contents($root.'/src/Service/AttachmentStorage.php'),
    'manager' => file_get_contents($root.'/src/Service/AttachmentManager.php'),
    'maintenance' => file_get_contents($root.'/src/EventSubscriber/MaintenanceModeSubscriber.php'),
    'databaseErrors' => file_get_contents($root.'/src/EventSubscriber/DatabaseExceptionSubscriber.php'),
    'requestId' => file_get_contents($root.'/src/EventSubscriber/RequestIdSubscriber.php'),
    'services' => file_get_contents($root.'/config/services.yaml'),
    'validation' => file_get_contents($root.'/scripts/validate.ps1'),
    'packager' => file_get_contents($root.'/scripts/package-release.ps1'),
    'packageVerifier' => file_get_contents($root.'/scripts/verify-release-package.ps1'),
];
foreach ($files as $name => $content) {
    if (!is_string($content)) {
        fwrite(STDERR, 'File M9.2-C non leggibile: '.$name.PHP_EOL);
        exit(1);
    }
}

$checks = [
    [str_contains($files['transaction'], 'wrapInTransaction'), 'transazione Doctrine'],
    [substr_count($files['transaction'], '$entityManager->flush();') >= 2, 'doppio flush interno alla transazione'],
    [str_contains($files['transaction'], '$this->auditLogger->record'), 'audit database nella transazione'],
    [str_contains($files['transaction'], '$this->auditLogger->mirror'), 'mirror dopo commit'],
    [str_contains($files['transaction'], 'PRAGMA busy_timeout = 5000'), 'busy timeout SQLite'],
    [str_contains($files['transaction'], 'PRAGMA foreign_keys = ON'), 'foreign keys SQLite'],
    [str_contains($files['audit'], 'public function record(AuditRecord $record): AuditLog'), 'registrazione audit separata'],
    [str_contains($files['timer'], 'TimerMutationLock') && str_contains($files['timer'], 'acquireExclusive()'), 'lock esclusivo timer'],
    [str_contains($files['timerLock'], 'ApplicationBusyException') && str_contains($files['timerLock'], 'tryAcquireExclusive()'), 'timer fail-fast se occupato'],
    [str_contains($files['attachmentLock'], 'ApplicationBusyException') && str_contains($files['attachmentLock'], 'tryAcquireShared()'), 'documenti fail-fast durante backup/ripristino'],
    [substr_count($files['timeController'], 'TimerMutationLock $mutationLock') >= 2, 'lock su inserimento e modifica ore'],
    [str_contains($files['storage'], 'public function quarantine') && str_contains($files['storage'], 'public function restore') && str_contains($files['storage'], 'public function purge'), 'quarantena allegati'],
    [str_contains($files['manager'], '$this->storage->restore($quarantined)'), 'compensazione eliminazione allegato'],
    [str_contains($files['maintenance'], 'tryAcquireShared()'), 'richieste non bloccanti durante manutenzione'],
    [str_contains($files['databaseErrors'], 'UniqueConstraintViolationException') && str_contains($files['databaseErrors'], 'database is locked'), 'errori database uniformi'],
    [str_contains($files['requestId'], "public const HEADER = 'X-Request-ID';"), 'request id'],
    [str_contains($files['services'], "app.version: '0.9.2-M9.2-H'"), 'versione M9.2-C'],
    [str_contains($files['validation'], 'scripts/m92c-robustness-contract.php'), 'contratto M9.2-C nel gate'],
    [str_contains($files['validation'], 'M9.2-H VALIDATION PASSED'), 'gate M9.2-C'],
    [str_contains($files['packager'], "'src/Service/AuditedTransaction.php'") && str_contains($files['packager'], "'docs/ROBUSTNESS.md'"), 'inventario M9.2-C nel packager'],
    [str_contains($files['packageVerifier'], "'tests/Project/RobustnessContractTest.php'") && str_contains($files['packageVerifier'], "'scripts/m92c-robustness-contract.php'"), 'inventario M9.2-C nel verificatore ZIP'],
];
foreach ($checks as [$ok, $description]) {
    if (!$ok) {
        fwrite(STDERR, 'Contratto M9.2-C non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

foreach (['error405.html.twig', 'error409.html.twig', 'error422.html.twig', 'error500.html.twig', 'error503.html.twig'] as $template) {
    $path = $root.'/templates/bundles/TwigBundle/Exception/'.$template;
    if (!is_file($path) || 0 === filesize($path)) {
        fwrite(STDERR, 'Template errore mancante o vuoto: '.$template.PHP_EOL);
        exit(1);
    }
}

foreach (['ProjectController.php', 'ActivityController.php', 'ClientController.php', 'UserController.php', 'EconomicsController.php', 'TimeEntryController.php'] as $controller) {
    $source = file_get_contents($root.'/src/Controller/'.$controller);
    if (!is_string($source) || !str_contains($source, 'AuditedTransaction')) {
        fwrite(STDERR, 'Controller non transazionale: '.$controller.PHP_EOL);
        exit(1);
    }
    if (1 === preg_match('/->(?:save|remove)\([^;\n]*,\s*true\)/', $source)) {
        fwrite(STDERR, 'Flush autonomo prima dell’audit in '.$controller.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-C robustezza superato.'.PHP_EOL;
