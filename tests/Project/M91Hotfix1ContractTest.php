<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class M91Hotfix1ContractTest extends TestCase
{
    public function testPhpstanFiltersBackupUsabilityAndClientDueReportRemainCovered(): void
    {
        $root = dirname(__DIR__, 2);
        $backupManager = (string) file_get_contents($root.'/src/Service/BackupManager.php');
        $lockManager = (string) file_get_contents($root.'/src/Service/FileLockManager.php');
        $timeController = (string) file_get_contents($root.'/src/Controller/TimeEntryController.php');
        $economicsController = (string) file_get_contents($root.'/src/Controller/EconomicsController.php');
        $economicsTemplate = (string) file_get_contents($root.'/templates/economics/index.html.twig');
        $verifyScript = (string) file_get_contents($root.'/scripts/verify-backup.ps1');

        self::assertStringContainsString('if (!$item instanceof \SplFileInfo)', $backupManager);
        self::assertStringContainsString('@param int<0, 7> $operation', $lockManager);
        self::assertStringNotContainsString("query->getInt('project')", $timeController);
        self::assertStringNotContainsString("query->getInt('activity')", $timeController);
        self::assertStringNotContainsString("query->getInt('user')", $timeController);
        self::assertStringContainsString('summarizeByClient($summaries)', $economicsController);
        self::assertStringContainsString('Importi dovuti per cliente', $economicsTemplate);
        self::assertStringContainsString('Resolve-StudioBackupArchive -ArchivePath $Archive', $verifyScript);
    }
}
