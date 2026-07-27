<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use PDO;

/**
 * @phpstan-type AttachmentManifest array{storageKey: string, sizeBytes: int, sha256: string}
 * @phpstan-type DatabaseManifest array{file: string, sizeBytes: int, sha256: string, migrations: list<string>}
 * @phpstan-type AttachmentsManifest array{directory: string, count: int, totalBytes: int, files: list<AttachmentManifest>}
 * @phpstan-type BackupManifest array{format: string, appVersion: string, createdAt: string, database: DatabaseManifest, attachments: AttachmentsManifest}
 */
final readonly class BackupManager
{
    public const FORMAT = 'studio-commesse-backup-v1';
    private const DATABASE_FILE = 'database.sqlite';
    private const ATTACHMENTS_DIRECTORY = 'attachments';
    private const MANIFEST_FILE = 'manifest.json';

    public function __construct(
        private Connection $connection,
        private AttachmentStorage $attachmentStorage,
        private AttachmentMutationLock $attachmentLock,
        private RequestRuntimeLock $requestLock,
        private MaintenanceMode $maintenanceMode,
        private string $attachmentStorageDirectory,
        private string $appVersion,
    ) {
    }

    public function create(string $destinationDirectory): BackupSummary
    {
        $lock = $this->attachmentLock->acquireExclusive();
        try {
            return $this->createUnlocked($destinationDirectory);
        } finally {
            $lock->release();
        }
    }

    public function verify(string $backupDirectory): BackupSummary
    {
        $directory = $this->normalizeExistingDirectory($backupDirectory);
        $manifest = $this->readManifest($directory);
        $databasePath = $directory.DIRECTORY_SEPARATOR.self::DATABASE_FILE;
        $attachmentsPath = $directory.DIRECTORY_SEPARATOR.self::ATTACHMENTS_DIRECTORY;

        $this->assertRegularFile($databasePath, 'Database del backup mancante.');
        $this->assertHashAndSize(
            $databasePath,
            $manifest['database']['sha256'],
            $manifest['database']['sizeBytes'],
            'Il database del backup non corrisponde al manifest.',
        );
        $this->assertSqliteIntegrity($databasePath);
        if ($manifest['database']['migrations'] !== $this->readMigrationVersions($databasePath)) {
            throw new \RuntimeException('Le migrazioni dichiarate nel manifest non corrispondono al database del backup.');
        }

        if (is_link($attachmentsPath)
            || ($manifest['attachments']['count'] > 0 && !is_dir($attachmentsPath))
            || (file_exists($attachmentsPath) && !is_dir($attachmentsPath))
        ) {
            throw new \RuntimeException('Directory allegati del backup mancante o non valida.');
        }
        $this->assertTopLevelInventory($directory, is_dir($attachmentsPath));

        $databaseAttachments = $this->readAttachmentRows($databasePath);
        $manifestAttachments = $manifest['attachments']['files'];
        if ($databaseAttachments !== $manifestAttachments) {
            throw new \RuntimeException('Il manifest degli allegati non corrisponde al database del backup.');
        }

        $expectedKeys = [];
        $totalBytes = 0;
        foreach ($manifestAttachments as $attachment) {
            $storageKey = $this->validateStorageKey($attachment['storageKey']);
            $file = $attachmentsPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
            $this->assertRegularFile($file, 'Allegato mancante nel backup: '.$storageKey);
            $this->assertHashAndSize(
                $file,
                $attachment['sha256'],
                $attachment['sizeBytes'],
                'Allegato non coerente con il manifest: '.$storageKey,
            );
            $expectedKeys[] = $storageKey;
            $totalBytes += $attachment['sizeBytes'];
        }

        $actualKeys = $this->listRelativeFiles($attachmentsPath);
        sort($expectedKeys);
        sort($actualKeys);
        if ($expectedKeys !== $actualKeys) {
            throw new \RuntimeException('Il backup contiene allegati mancanti o file non referenziati.');
        }
        if ($manifest['attachments']['count'] !== count($manifestAttachments)
            || $manifest['attachments']['totalBytes'] !== $totalBytes
        ) {
            throw new \RuntimeException('I totali degli allegati nel manifest non sono coerenti.');
        }

        return $this->summary($directory, $manifest);
    }

    public function restore(string $backupDirectory, string $safetyBackupDirectory): RestoreResult
    {
        $verified = $this->verify($backupDirectory);
        if (file_exists($safetyBackupDirectory)) {
            throw new \RuntimeException('La destinazione del backup di sicurezza esiste già.');
        }

        $ownsMaintenanceMode = !$this->maintenanceMode->isEnabled();
        if ($ownsMaintenanceMode) {
            $this->maintenanceMode->enable('Ripristino coordinato di database e documenti in corso.');
        }
        /** @var FileLock|null $requestLock */
        $requestLock = null;
        /** @var FileLock|null $attachmentLock */
        $attachmentLock = null;
        $completed = false;

        try {
            $requestLock = $this->requestLock->acquireExclusive();
            $attachmentLock = $this->attachmentLock->acquireExclusive();
            $safetyBackup = $this->createUnlocked($safetyBackupDirectory);
            $manifest = $this->readManifest($verified->directory);
            $this->replaceLiveState($verified->directory, $manifest);
            $completed = true;

            return new RestoreResult($verified, $safetyBackup);
        } finally {
            $attachmentLock?->release();
            $requestLock?->release();
            if ($completed && $ownsMaintenanceMode) {
                $this->maintenanceMode->disable();
            }
        }
    }

    private function createUnlocked(string $destinationDirectory): BackupSummary
    {
        $destination = $this->prepareNewDirectory($destinationDirectory);
        $databasePath = $destination.DIRECTORY_SEPARATOR.self::DATABASE_FILE;
        $attachmentsPath = $destination.DIRECTORY_SEPARATOR.self::ATTACHMENTS_DIRECTORY;

        try {
            if (!mkdir($attachmentsPath, 0700, true) && !is_dir($attachmentsPath)) {
                throw new \RuntimeException('Impossibile creare la directory allegati del backup.');
            }

            $this->snapshotDatabase($databasePath);
            $attachments = $this->readAttachmentRows($databasePath);
            $totalBytes = 0;
            foreach ($attachments as $attachment) {
                $storageKey = $this->validateStorageKey($attachment['storageKey']);
                $source = $this->attachmentStorage->resolve($storageKey);
                $this->assertHashAndSize(
                    $source,
                    $attachment['sha256'],
                    $attachment['sizeBytes'],
                    'Lo spazio documentale non corrisponde ai metadati del database: '.$storageKey,
                );

                $target = $attachmentsPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
                $targetDirectory = dirname($target);
                if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0700, true) && !is_dir($targetDirectory)) {
                    throw new \RuntimeException('Impossibile creare la struttura allegati nel backup.');
                }
                if (!copy($source, $target)) {
                    throw new \RuntimeException('Impossibile copiare nel backup l’allegato: '.$storageKey);
                }
                @chmod($target, 0600);
                $totalBytes += $attachment['sizeBytes'];
            }

            $databaseHash = hash_file('sha256', $databasePath);
            $databaseBytes = filesize($databasePath);
            if (!is_string($databaseHash) || !is_int($databaseBytes)) {
                throw new \RuntimeException('Impossibile calcolare i metadati del database di backup.');
            }

            $manifest = [
                'format' => self::FORMAT,
                'appVersion' => $this->appVersion,
                'createdAt' => (new \DateTimeImmutable())->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
                'database' => [
                    'file' => self::DATABASE_FILE,
                    'sizeBytes' => $databaseBytes,
                    'sha256' => $databaseHash,
                    'migrations' => $this->readMigrationVersions($databasePath),
                ],
                'attachments' => [
                    'directory' => self::ATTACHMENTS_DIRECTORY,
                    'count' => count($attachments),
                    'totalBytes' => $totalBytes,
                    'files' => $attachments,
                ],
            ];
            $this->writeManifest($destination, $manifest);

            return $this->verify($destination);
        } catch (\Throwable $exception) {
            $this->removeDirectory($destination);
            throw $exception;
        }
    }

    /** @param BackupManifest $manifest */
    private function replaceLiveState(string $backupDirectory, array $manifest): void
    {
        $databaseTarget = $this->databasePath();
        $databaseParent = dirname($databaseTarget);
        if (!is_dir($databaseParent) && !mkdir($databaseParent, 0700, true) && !is_dir($databaseParent)) {
            throw new \RuntimeException('Impossibile preparare la directory del database.');
        }

        $storageTarget = rtrim($this->attachmentStorageDirectory, '/\\');
        $storageParent = dirname($storageTarget);
        if (!is_dir($storageParent) && !mkdir($storageParent, 0700, true) && !is_dir($storageParent)) {
            throw new \RuntimeException('Impossibile preparare la directory dello spazio documentale.');
        }

        $suffix = bin2hex(random_bytes(8));
        $incomingDatabase = $databaseTarget.'.restore-new-'.$suffix;
        $oldDatabase = $databaseTarget.'.restore-old-'.$suffix;
        $incomingStorage = $storageTarget.'.restore-new-'.$suffix;
        $oldStorage = $storageTarget.'.restore-old-'.$suffix;
        $databaseMoved = false;
        $storageMoved = false;
        $newDatabaseInstalled = false;
        $newStorageInstalled = false;

        try {
            if (!copy($backupDirectory.DIRECTORY_SEPARATOR.self::DATABASE_FILE, $incomingDatabase)) {
                throw new \RuntimeException('Impossibile preparare il database da ripristinare.');
            }
            @chmod($incomingDatabase, 0600);
            if (!mkdir($incomingStorage, 0700, true) && !is_dir($incomingStorage)) {
                throw new \RuntimeException('Impossibile preparare lo spazio documentale da ripristinare.');
            }
            $this->copyManifestAttachments($backupDirectory, $incomingStorage, $manifest['attachments']['files']);

            try {
                $this->connection->executeStatement('PRAGMA wal_checkpoint(TRUNCATE)');
            } catch (\Throwable) {
                // Il database può non usare WAL: la copia VACUUM resta comunque coerente.
            }
            $this->connection->close();

            if (is_file($databaseTarget)) {
                if (!rename($databaseTarget, $oldDatabase)) {
                    throw new \RuntimeException('Impossibile mettere in sicurezza il database corrente.');
                }
                $databaseMoved = true;
            }
            $this->removeSqliteSidecars($databaseTarget);
            if (!rename($incomingDatabase, $databaseTarget)) {
                throw new \RuntimeException('Impossibile installare il database ripristinato.');
            }
            $newDatabaseInstalled = true;

            if (is_dir($storageTarget)) {
                if (!rename($storageTarget, $oldStorage)) {
                    throw new \RuntimeException('Impossibile mettere in sicurezza gli allegati correnti.');
                }
                $storageMoved = true;
            }
            if (!rename($incomingStorage, $storageTarget)) {
                throw new \RuntimeException('Impossibile installare gli allegati ripristinati.');
            }
            $newStorageInstalled = true;

            $this->assertRestoredState($databaseTarget, $storageTarget, $manifest);

            if ($databaseMoved && is_file($oldDatabase)) {
                unlink($oldDatabase);
            }
            if ($storageMoved) {
                $this->removeDirectory($oldStorage);
            }
            $this->removeSqliteSidecars($databaseTarget);
        } catch (\Throwable $exception) {
            if ($newStorageInstalled) {
                $this->removeDirectory($storageTarget);
            }
            if ($storageMoved && is_dir($oldStorage)) {
                @rename($oldStorage, $storageTarget);
            }
            if ($newDatabaseInstalled && is_file($databaseTarget)) {
                @unlink($databaseTarget);
            }
            if ($databaseMoved && is_file($oldDatabase)) {
                @rename($oldDatabase, $databaseTarget);
            }
            $this->removeSqliteSidecars($databaseTarget);
            throw $exception;
        } finally {
            if (is_file($incomingDatabase)) {
                @unlink($incomingDatabase);
            }
            if (is_dir($incomingStorage)) {
                $this->removeDirectory($incomingStorage);
            }
        }
    }

    private function snapshotDatabase(string $target): void
    {
        if (file_exists($target)) {
            throw new \RuntimeException('Il file database del backup esiste già.');
        }
        $databasePath = $this->databasePath();
        if (!is_file($databasePath)) {
            throw new \RuntimeException('Il database SQLite locale non è disponibile.');
        }

        $quotedTarget = $this->connection->quote($target);
        $this->connection->executeStatement('VACUUM INTO '.$quotedTarget);
        $this->assertSqliteIntegrity($target);
        @chmod($target, 0600);
    }

    private function databasePath(): string
    {
        try {
            $databases = $this->connection->fetchAllAssociative('PRAGMA database_list');
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Il backup coordinato supporta esclusivamente SQLite.', 0, $exception);
        }

        foreach ($databases as $database) {
            if ('main' !== ($database['name'] ?? null)) {
                continue;
            }

            $path = $database['file'] ?? null;
            if (is_string($path) && '' !== trim($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('DATABASE_URL deve indicare un database SQLite locale persistente.');
    }

    /** @return list<AttachmentManifest> */
    private function readAttachmentRows(string $databasePath): array
    {
        $pdo = $this->openSqlite($databasePath);
        $statement = $pdo->query('SELECT storage_key, size_bytes, sha256 FROM attachment ORDER BY storage_key');
        if (false === $statement) {
            throw new \RuntimeException('Impossibile leggere gli allegati dal database di backup.');
        }

        $rows = [];
        while (false !== ($row = $statement->fetch(PDO::FETCH_ASSOC))) {
            if (!is_array($row)) {
                continue;
            }
            $storageKey = $this->validateStorageKey((string) ($row['storage_key'] ?? ''));
            $sizeBytes = (int) ($row['size_bytes'] ?? -1);
            $sha256 = strtolower((string) ($row['sha256'] ?? ''));
            if ($sizeBytes < 0 || 1 !== preg_match('/^[a-f0-9]{64}$/', $sha256)) {
                throw new \RuntimeException('Metadati allegato non validi nel database: '.$storageKey);
            }
            $rows[] = [
                'storageKey' => $storageKey,
                'sizeBytes' => $sizeBytes,
                'sha256' => $sha256,
            ];
        }

        return $rows;
    }

    /** @return list<string> */
    private function readMigrationVersions(string $databasePath): array
    {
        $pdo = $this->openSqlite($databasePath);
        $exists = $pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'doctrine_migration_versions'");
        if (false === $exists || false === $exists->fetchColumn()) {
            return [];
        }

        $statement = $pdo->query('SELECT version FROM doctrine_migration_versions ORDER BY version');
        if (false === $statement) {
            return [];
        }

        $versions = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $version) {
            if (is_string($version)) {
                $versions[] = $version;
            }
        }

        return $versions;
    }

    private function assertSqliteIntegrity(string $databasePath): void
    {
        $pdo = $this->openSqlite($databasePath);
        $integrity = $pdo->query('PRAGMA integrity_check');
        if (false === $integrity || 'ok' !== $integrity->fetchColumn()) {
            throw new \RuntimeException('Il controllo di integrità SQLite del backup non è riuscito.');
        }
        $foreignKeys = $pdo->query('PRAGMA foreign_key_check');
        if (false === $foreignKeys || false !== $foreignKeys->fetch(PDO::FETCH_ASSOC)) {
            throw new \RuntimeException('Il database del backup contiene violazioni delle chiavi esterne.');
        }
    }

    private function openSqlite(string $databasePath): PDO
    {
        $pdo = new PDO('sqlite:'.$databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    /** @param BackupManifest $manifest */
    private function writeManifest(string $directory, array $manifest): void
    {
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $path = $directory.DIRECTORY_SEPARATOR.self::MANIFEST_FILE;
        if (false === file_put_contents($path, $json."\n", LOCK_EX)) {
            throw new \RuntimeException('Impossibile scrivere il manifest del backup.');
        }
        @chmod($path, 0600);
    }

    /** @return BackupManifest */
    private function readManifest(string $directory): array
    {
        $path = $directory.DIRECTORY_SEPARATOR.self::MANIFEST_FILE;
        $this->assertRegularFile($path, 'Manifest del backup mancante.');
        $content = file_get_contents($path);
        if (!is_string($content)) {
            throw new \RuntimeException('Manifest del backup non leggibile.');
        }

        try {
            $decoded = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Manifest del backup non valido.', 0, $exception);
        }
        if (!is_array($decoded) || self::FORMAT !== ($decoded['format'] ?? null)) {
            throw new \RuntimeException('Formato di backup non supportato.');
        }

        $appVersion = $decoded['appVersion'] ?? null;
        $createdAt = $decoded['createdAt'] ?? null;
        $database = $decoded['database'] ?? null;
        $attachments = $decoded['attachments'] ?? null;
        if (!is_string($appVersion) || '' === $appVersion || !is_string($createdAt) || '' === $createdAt
            || !is_array($database) || !is_array($attachments)
        ) {
            throw new \RuntimeException('Manifest del backup incompleto.');
        }
        try {
            new \DateTimeImmutable($createdAt);
        } catch (\Throwable $exception) {
            throw new \RuntimeException('Data di creazione del backup non valida.', 0, $exception);
        }

        $databaseFile = $database['file'] ?? null;
        $databaseSize = $database['sizeBytes'] ?? null;
        $databaseHash = $database['sha256'] ?? null;
        $migrations = $database['migrations'] ?? null;
        if (self::DATABASE_FILE !== $databaseFile || !is_int($databaseSize) || $databaseSize < 0
            || !is_string($databaseHash) || 1 !== preg_match('/^[a-f0-9]{64}$/', $databaseHash)
            || !is_array($migrations)
        ) {
            throw new \RuntimeException('Sezione database del manifest non valida.');
        }
        $normalizedMigrations = [];
        foreach ($migrations as $migration) {
            if (!is_string($migration) || '' === $migration) {
                throw new \RuntimeException('Versione migrazione non valida nel manifest.');
            }
            $normalizedMigrations[] = $migration;
        }

        $attachmentsDirectory = $attachments['directory'] ?? null;
        $attachmentCount = $attachments['count'] ?? null;
        $attachmentBytes = $attachments['totalBytes'] ?? null;
        $files = $attachments['files'] ?? null;
        if (self::ATTACHMENTS_DIRECTORY !== $attachmentsDirectory || !is_int($attachmentCount) || $attachmentCount < 0
            || !is_int($attachmentBytes) || $attachmentBytes < 0 || !is_array($files)
        ) {
            throw new \RuntimeException('Sezione allegati del manifest non valida.');
        }

        $normalizedFiles = [];
        foreach ($files as $file) {
            if (!is_array($file)) {
                throw new \RuntimeException('Voce allegato del manifest non valida.');
            }
            $storageKey = $this->validateStorageKey((string) ($file['storageKey'] ?? ''));
            $sizeBytes = $file['sizeBytes'] ?? null;
            $sha256 = $file['sha256'] ?? null;
            if (!is_int($sizeBytes) || $sizeBytes < 0 || !is_string($sha256) || 1 !== preg_match('/^[a-f0-9]{64}$/', $sha256)) {
                throw new \RuntimeException('Metadati allegato del manifest non validi: '.$storageKey);
            }
            $normalizedFiles[] = ['storageKey' => $storageKey, 'sizeBytes' => $sizeBytes, 'sha256' => $sha256];
        }
        usort($normalizedFiles, self::compareAttachmentManifest(...));

        return [
            'format' => self::FORMAT,
            'appVersion' => $appVersion,
            'createdAt' => $createdAt,
            'database' => [
                'file' => self::DATABASE_FILE,
                'sizeBytes' => $databaseSize,
                'sha256' => $databaseHash,
                'migrations' => $normalizedMigrations,
            ],
            'attachments' => [
                'directory' => self::ATTACHMENTS_DIRECTORY,
                'count' => $attachmentCount,
                'totalBytes' => $attachmentBytes,
                'files' => $normalizedFiles,
            ],
        ];
    }

    /** @param list<AttachmentManifest> $attachments */
    private function copyManifestAttachments(string $backupDirectory, string $targetDirectory, array $attachments): void
    {
        $sourceRoot = $backupDirectory.DIRECTORY_SEPARATOR.self::ATTACHMENTS_DIRECTORY;
        foreach ($attachments as $attachment) {
            $storageKey = $this->validateStorageKey($attachment['storageKey']);
            $source = $sourceRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
            $target = $targetDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $storageKey);
            $parent = dirname($target);
            if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) {
                throw new \RuntimeException('Impossibile creare la struttura documentale da ripristinare.');
            }
            if (!copy($source, $target)) {
                throw new \RuntimeException('Impossibile copiare l’allegato da ripristinare: '.$storageKey);
            }
            @chmod($target, 0600);
        }
    }

    /** @param BackupManifest $manifest */
    private function assertRestoredState(string $databasePath, string $storagePath, array $manifest): void
    {
        $this->assertSqliteIntegrity($databasePath);
        $databaseAttachments = $this->readAttachmentRows($databasePath);
        if ($databaseAttachments !== $manifest['attachments']['files']) {
            throw new \RuntimeException('Il database ripristinato non corrisponde al manifest.');
        }

        foreach ($manifest['attachments']['files'] as $attachment) {
            $file = $storagePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $attachment['storageKey']);
            $this->assertRegularFile($file, 'Allegato ripristinato mancante: '.$attachment['storageKey']);
            $this->assertHashAndSize($file, $attachment['sha256'], $attachment['sizeBytes'], 'Allegato ripristinato non integro.');
        }
        $expected = [];
        foreach ($manifest['attachments']['files'] as $attachment) {
            $expected[] = $attachment['storageKey'];
        }
        $actual = $this->listRelativeFiles($storagePath);
        sort($expected);
        sort($actual);
        if ($expected !== $actual) {
            throw new \RuntimeException('Lo spazio documentale ripristinato contiene file inattesi.');
        }
    }

    /**
     * @param AttachmentManifest $left
     * @param AttachmentManifest $right
     */
    private static function compareAttachmentManifest(array $left, array $right): int
    {
        return $left['storageKey'] <=> $right['storageKey'];
    }

    private function assertTopLevelInventory(string $directory, bool $attachmentsDirectoryPresent): void
    {
        $expected = [self::DATABASE_FILE, self::MANIFEST_FILE];
        if ($attachmentsDirectoryPresent) {
            $expected[] = self::ATTACHMENTS_DIRECTORY;
        }
        sort($expected);

        $actual = [];
        $iterator = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                throw new \RuntimeException('Impossibile leggere un elemento di primo livello del backup.');
            }
            if ($item->isLink()) {
                throw new \RuntimeException('Il backup contiene collegamenti simbolici non consentiti.');
            }
            $actual[] = $item->getFilename();
        }
        sort($actual);
        if ($expected !== $actual) {
            throw new \RuntimeException('Il backup contiene elementi di primo livello inattesi.');
        }
    }

    private function assertRegularFile(string $path, string $message): void
    {
        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            throw new \RuntimeException($message);
        }
    }

    private function assertHashAndSize(string $path, string $expectedHash, int $expectedSize, string $message): void
    {
        $hash = hash_file('sha256', $path);
        $size = filesize($path);
        if (!is_string($hash) || !is_int($size) || !hash_equals($expectedHash, $hash) || $expectedSize !== $size) {
            throw new \RuntimeException($message);
        }
    }

    private function validateStorageKey(string $storageKey): string
    {
        if (1 !== preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.[a-z0-9]+$#', $storageKey)) {
            throw new \RuntimeException('Riferimento allegato non valido nel backup.');
        }

        return $storageKey;
    }

    /** @return list<string> */
    private function listRelativeFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $item) {
            if ($item->isLink() || !$item->isFile()) {
                throw new \RuntimeException('Il backup contiene elementi non regolari nello spazio allegati.');
            }
            $relative = substr($item->getPathname(), strlen(rtrim($root, '/\\')) + 1);
            $files[] = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        sort($files);

        return $files;
    }

    private function prepareNewDirectory(string $directory): string
    {
        $directory = rtrim($directory, '/\\');
        if ('' === $directory) {
            throw new \InvalidArgumentException('Specificare una directory di destinazione.');
        }
        if (file_exists($directory)) {
            throw new \RuntimeException('La directory di destinazione esiste già: '.$directory);
        }
        if (!mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossibile creare la directory di backup: '.$directory);
        }

        $real = realpath($directory);

        return is_string($real) ? $real : $directory;
    }

    private function normalizeExistingDirectory(string $directory): string
    {
        $real = realpath($directory);
        if (!is_string($real) || !is_dir($real) || is_link($directory)) {
            throw new \RuntimeException('Directory di backup non valida o inesistente.');
        }

        return $real;
    }

    /** @param BackupManifest $manifest */
    private function summary(string $directory, array $manifest): BackupSummary
    {
        return new BackupSummary(
            directory: $directory,
            createdAt: $manifest['createdAt'],
            appVersion: $manifest['appVersion'],
            attachmentCount: $manifest['attachments']['count'],
            attachmentBytes: $manifest['attachments']['totalBytes'],
            databaseBytes: $manifest['database']['sizeBytes'],
            databaseSha256: $manifest['database']['sha256'],
        );
    }

    private function removeSqliteSidecars(string $databasePath): void
    {
        foreach ([$databasePath.'-wal', $databasePath.'-shm', $databasePath.'-journal'] as $sidecar) {
            if (is_file($sidecar)) {
                @unlink($sidecar);
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($directory);
    }
}
