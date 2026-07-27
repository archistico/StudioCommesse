<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\AttachmentMutationLock;
use App\Service\AttachmentStorage;
use App\Service\BackupManager;
use App\Service\MaintenanceMode;
use App\Service\RequestRuntimeLock;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PDO;
use PHPUnit\Framework\TestCase;

final class BackupManagerTest extends TestCase
{
    private string $root;
    private string $databasePath;
    private string $storagePath;
    private string $maintenanceMarker;
    private Connection $connection;
    private BackupManager $manager;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/studio-commesse-backup-tests-'.bin2hex(random_bytes(8));
        $this->databasePath = $this->root.'/live.sqlite';
        $this->storagePath = $this->root.'/storage/attachments';
        $this->maintenanceMarker = $this->root.'/maintenance.lock';
        mkdir($this->storagePath, 0700, true);

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'path' => $this->databasePath,
        ]);
        $this->connection->executeStatement('CREATE TABLE doctrine_migration_versions (version VARCHAR(191) PRIMARY KEY, executed_at DATETIME DEFAULT NULL, execution_time INTEGER DEFAULT NULL)');
        $this->connection->executeStatement("INSERT INTO doctrine_migration_versions (version) VALUES ('DoctrineMigrations\\\\VersionTest')");
        $this->connection->executeStatement('CREATE TABLE attachment (id INTEGER PRIMARY KEY AUTOINCREMENT, storage_key VARCHAR(255) NOT NULL, size_bytes INTEGER NOT NULL, sha256 VARCHAR(64) NOT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_state (name VARCHAR(50) PRIMARY KEY, value VARCHAR(255) NOT NULL)');

        $this->manager = new BackupManager(
            connection: $this->connection,
            attachmentStorage: new AttachmentStorage($this->storagePath),
            attachmentLock: new AttachmentMutationLock($this->root.'/locks/attachments.lock'),
            requestLock: new RequestRuntimeLock($this->root.'/locks/requests.lock'),
            maintenanceMode: new MaintenanceMode($this->maintenanceMarker),
            attachmentStorageDirectory: $this->storagePath,
            appVersion: 'test-version',
        );
    }

    public function testCreateAndVerifyConsistentDatabaseAndAttachments(): void
    {
        $this->writeState('originale', 'contenuto originale');
        $backup = $this->root.'/backup';

        $created = $this->manager->create($backup);
        $verified = $this->manager->verify($backup);

        self::assertSame('test-version', $created->appVersion);
        self::assertSame(1, $created->attachmentCount);
        self::assertSame($created->databaseSha256, $verified->databaseSha256);
        self::assertFileExists($backup.'/manifest.json');
        self::assertFileExists($backup.'/database.sqlite');
        self::assertFileExists($backup.'/attachments/'.$this->storageKey());

        file_put_contents($backup.'/attachments/'.$this->storageKey(), 'manomesso');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Allegato non coerente con il manifest');
        $this->manager->verify($backup);
    }

    public function testRestoreReplacesLiveStateAndPreservesAutomaticSafetyBackup(): void
    {
        $this->writeState('originale', 'contenuto originale');
        $backup = $this->root.'/backup-originale';
        $this->manager->create($backup);

        $this->writeState('modificato', 'contenuto modificato');
        $safety = $this->root.'/backup-sicurezza';
        $result = $this->manager->restore($backup, $safety);

        self::assertSame(realpath($backup), $result->restoredBackup->directory);
        self::assertSame(realpath($safety), $result->safetyBackup->directory);
        self::assertFalse(is_file($this->maintenanceMarker));

        $restoredPdo = $this->openDatabase($this->databasePath);
        self::assertSame('originale', $restoredPdo->query("SELECT value FROM app_state WHERE name = 'status'")?->fetchColumn());
        self::assertSame('contenuto originale', file_get_contents($this->storagePath.'/'.$this->storageKey()));

        $safetySummary = $this->manager->verify($safety);
        self::assertSame(1, $safetySummary->attachmentCount);
        $safetyPdo = $this->openDatabase($safety.'/database.sqlite');
        self::assertSame('modificato', $safetyPdo->query("SELECT value FROM app_state WHERE name = 'status'")?->fetchColumn());
        self::assertSame('contenuto modificato', file_get_contents($safety.'/attachments/'.$this->storageKey()));
    }

    public function testVerifyRejectsMigrationInventoryThatDoesNotMatchTheDatabase(): void
    {
        $this->writeState('originale', 'contenuto originale');
        $backup = $this->root.'/backup-migrazioni';
        $this->manager->create($backup);

        $manifestPath = $backup.'/manifest.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true, 64, JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);
        self::assertIsArray($manifest['database'] ?? null);
        $manifest['database']['migrations'] = [];
        file_put_contents(
            $manifestPath,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n",
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('migrazioni dichiarate nel manifest');
        $this->manager->verify($backup);
    }

    private function writeState(string $state, string $fileContent): void
    {
        $directory = $this->storagePath.'/2026/07';
        if (!is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $this->storagePath.'/'.$this->storageKey();
        file_put_contents($path, $fileContent);
        $hash = hash_file('sha256', $path);
        $size = filesize($path);
        self::assertIsString($hash);
        self::assertIsInt($size);

        $this->connection->executeStatement('DELETE FROM attachment');
        $this->connection->executeStatement('DELETE FROM app_state');
        $this->connection->executeStatement(
            'INSERT INTO attachment (storage_key, size_bytes, sha256) VALUES (?, ?, ?)',
            [$this->storageKey(), $size, $hash],
        );
        $this->connection->executeStatement(
            'INSERT INTO app_state (name, value) VALUES (?, ?)',
            ['status', $state],
        );
    }

    private function storageKey(): string
    {
        return '2026/07/0123456789abcdef0123456789abcdef.pdf';
    }

    private function openDatabase(string $path): PDO
    {
        $pdo = new PDO('sqlite:'.$path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    protected function tearDown(): void
    {
        $this->connection->close();
        $this->removeDirectory($this->root);
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
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
