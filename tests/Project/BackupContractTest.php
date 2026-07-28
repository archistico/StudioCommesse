<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class BackupContractTest extends TestCase
{
    public function testM91BackupRestoreContractsArePresent(): void
    {
        $root = dirname(__DIR__, 2);
        $services = Yaml::parseFile($root.'/config/services.yaml');
        self::assertIsArray($services);
        self::assertSame('0.9.2-M9.2-H', $services['parameters']['app.version'] ?? null);

        $manager = file_get_contents($root.'/src/Service/BackupManager.php');
        self::assertIsString($manager);
        self::assertStringContainsString("studio-commesse-backup-v1", $manager);
        self::assertStringContainsString("VACUUM INTO", $manager);
        self::assertStringContainsString("PRAGMA database_list", $manager);
        self::assertStringContainsString("PRAGMA integrity_check", $manager);
        self::assertStringContainsString("PRAGMA foreign_key_check", $manager);
        self::assertStringContainsString('readMigrationVersions($databasePath)', $manager);
        self::assertStringContainsString('createUnlocked($safetyBackupDirectory)', $manager);
        self::assertStringContainsString("maintenanceMode->enable", $manager);
        self::assertStringContainsString('$completed && $ownsMaintenanceMode', $manager);

        $attachmentManager = file_get_contents($root.'/src/Service/AttachmentManager.php');
        self::assertIsString($attachmentManager);
        self::assertStringContainsString('AttachmentMutationLock', $attachmentManager);
        self::assertGreaterThanOrEqual(2, substr_count($attachmentManager, 'acquireShared()'));
    }

    public function testBackupPowerShellScriptsProtectArchiveExtractionAndConfirmation(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['backup.ps1', 'verify-backup.ps1', 'restore-backup.ps1', 'clear-maintenance.ps1', 'backup-common.ps1'] as $scriptName) {
            self::assertFileExists($root.'/scripts/'.$scriptName);
        }

        $common = file_get_contents($root.'/scripts/backup-common.ps1');
        $restore = file_get_contents($root.'/scripts/restore-backup.ps1');
        self::assertIsString($common);
        self::assertIsString($restore);
        self::assertStringContainsString('StartsWith($root', $common);
        self::assertStringContainsString('collegamento simbolico non consentito', $common);
        self::assertStringContainsString('Nome di file non sicuro nell\'archivio', $common);
        self::assertStringContainsString('Nome di dispositivo Windows non consentito', $common);
        self::assertStringContainsString('[System.IO.Compression.ZipFile]::CreateFromDirectory', $common);
        self::assertStringContainsString('Resolve-StudioBackupArchive', $common);
        self::assertStringContainsString('StudioCommesse_Backup_*.zip', $common);
        $verify = file_get_contents($root.'/scripts/verify-backup.ps1');
        self::assertIsString($verify);
        self::assertStringContainsString('[string]$Archive = ""', $verify);
        self::assertStringContainsString('Resolve-StudioBackupArchive -ArchivePath $Archive', $verify);
        self::assertStringContainsString('$Confirm -ne "RESTORE"', $restore);
        self::assertStringContainsString('StudioCommesse_PreRestore_', $restore);
        self::assertStringContainsString('app:maintenance:enable', $restore);
        self::assertStringContainsString('app:maintenance:disable', $restore);
    }
}
