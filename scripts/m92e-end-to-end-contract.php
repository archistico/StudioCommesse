<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = [
    'services' => 'config/services.yaml',
    'validation' => 'scripts/validate.ps1',
    'packager' => 'scripts/package-release.ps1',
    'verifier' => 'scripts/verify-release-package.ps1',
    'workflow' => 'tests/Controller/EndToEndWorkflowTest.php',
    'backup' => 'tests/Service/BackupManagerTest.php',
    'projectTest' => 'tests/Project/EndToEndWorkflowContractTest.php',
    'docs' => 'docs/END_TO_END_FLOWS.md',
    'readme' => 'README.md',
];

$contents = [];
foreach ($paths as $name => $relativePath) {
    $value = file_get_contents($root.'/'.$relativePath);
    if (false === $value) {
        fwrite(STDERR, 'File M9.2-E non leggibile: '.$relativePath.PHP_EOL);
        exit(1);
    }
    $contents[$name] = str_replace("\r\n", "\n", $value);
}

$workflowMethods = [
    'testCompletePaidProjectCanBeReviewedAndArchived',
    'testCollaboratorCanCreateAssignedWorkAndRecordOwnHoursWithoutFinancialExposure',
    'testClosedProjectWithOpenActivityIsReportedAsInconsistent',
    'testArchivedProjectRequiresClientRestorationBeforeProjectRestoration',
];

$checks = [
    [str_contains($contents['services'], "app.version: '0.9.2-M9.2-H'"), 'versione M9.2-E'],
    [str_contains($contents['validation'], 'scripts/m92e-end-to-end-contract.php'), 'contratto M9.2-E nel gate'],
    [str_contains($contents['validation'], 'M9.2-H VALIDATION PASSED'), 'gate M9.2-E'],
    [str_contains($contents['packager'], 'StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip'), 'nome package M9.2-E'],
    [str_contains($contents['packager'], "'tests/Controller/EndToEndWorkflowTest.php'"), 'test workflow obbligatorio nel package'],
    [str_contains($contents['packager'], "'tests/Project/EndToEndWorkflowContractTest.php'"), 'test contratto obbligatorio nel package'],
    [str_contains($contents['packager'], "'scripts/m92e-end-to-end-contract.php'"), 'script contratto obbligatorio nel package'],
    [str_contains($contents['packager'], "'docs/END_TO_END_FLOWS.md'"), 'documentazione obbligatoria nel package'],
    [str_contains($contents['verifier'], "'tests/Controller/EndToEndWorkflowTest.php'"), 'test workflow verificato nel package'],
    [str_contains($contents['verifier'], "'docs/END_TO_END_FLOWS.md'"), 'documentazione verificata nel package'],
    [str_contains($contents['backup'], 'testBackupRestorePreservesACompleteBusinessGraph'), 'scenario backup completo'],
    [str_contains($contents['backup'], "INSERT INTO audit_log VALUES (1, 'project.archived'"), 'audit nel grafo di backup'],
    [str_contains($contents['backup'], 'allegato del flusso completo'), 'allegato nel grafo di backup'],
    [str_contains($contents['workflow'], '$this->entityManager->find(Project::class, $projectId)'), 'ricaricamento Doctrine della commessa dopo le richieste HTTP'],
    [str_contains($contents['workflow'], '$this->entityManager->find(Client::class, $clientId)'), 'ricaricamento Doctrine del cliente dopo le richieste HTTP'],
    [!str_contains($contents['workflow'], '$this->entityManager->refresh($project)'), 'nessun refresh di commesse detached'],
    [!str_contains($contents['workflow'], '$this->entityManager->refresh($client)'), 'nessun refresh di clienti detached'],
    [str_contains($contents['docs'], 'Commessa completa e pagata'), 'scenario commessa documentato'],
    [str_contains($contents['docs'], 'Chiusura incoerente'), 'scenario incoerenza documentato'],
    [str_contains($contents['docs'], 'Backup e ripristino'), 'scenario backup documentato'],
    [!preg_match('/\bM\d+(?:\.\d+)*(?:-[A-Z0-9.]+)?\b|baseline|candidate|VALIDATION PASSED/i', $contents['readme']), 'README senza cronologia milestone'],
];

foreach ($workflowMethods as $method) {
    $checks[] = [str_contains($contents['workflow'], 'function '.$method.'('), 'scenario '.$method];
}

foreach ($checks as [$ok, $description]) {
    if (!$ok) {
        fwrite(STDERR, 'Contratto M9.2-E non rispettato: '.$description.PHP_EOL);
        exit(1);
    }
}

echo 'Contratto M9.2-E flussi end-to-end superato.'.PHP_EOL;
