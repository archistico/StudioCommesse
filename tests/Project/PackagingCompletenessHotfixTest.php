<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class PackagingCompletenessHotfixTest extends TestCase
{
    public function testM92AHotfix2RequiresACompleteNonEmptyApplicationPackage(): void
    {
        $root = dirname(__DIR__, 2);
        $packager = (string) file_get_contents($root.'/scripts/package-release.ps1');
        $verifier = (string) file_get_contents($root.'/scripts/verify-release-package.ps1');
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');

        foreach ([
            'src/Kernel.php',
            'src/Controller/UserController.php',
            'src/Entity/User.php',
            'src/Repository/UserRepository.php',
            'src/Security/ActiveUserChecker.php',
            'src/Service/AttachmentManager.php',
            'templates/layout/app.html.twig',
            'templates/user/index.html.twig',
            'tests/Controller/AttachmentManagementTest.php',
        ] as $required) {
            self::assertStringContainsString($required, $packager);
            self::assertStringContainsString($required, $verifier);
            self::assertFileExists($root.'/'.$required);
            self::assertGreaterThan(0, filesize($root.'/'.$required));
        }

        foreach ([
            "Pattern = '^src/Entity/.+\\.php$'",
            "Pattern = '^src/Repository/.+\\.php$'",
            "Pattern = '^src/Security/.+\\.php$'",
            "Pattern = '^templates/.+\\.twig$'",
            "Pattern = '^tests/.+\\.php$'",
            "Pattern = '^migrations/.+\\.php$'",
        ] as $familyContract) {
            self::assertStringContainsString($familyContract, $verifier);
        }

        self::assertStringContainsString('Inventario pacchetto diverso dal sorgente distribuibile', $verifier);
        self::assertStringContainsString('file critico vuoto', $verifier);
        self::assertStringContainsString('verify-release-package.ps1', $validation);
        self::assertStringContainsString('M9.2-H VALIDATION PASSED', $validation);
    }

    public function testKnownFalsePositivesRemainCoveredByApplicationContracts(): void
    {
        $root = dirname(__DIR__, 2);
        $userController = (string) file_get_contents($root.'/src/Controller/UserController.php');
        $attachmentManager = (string) file_get_contents($root.'/src/Service/AttachmentManager.php');
        $attachmentTest = (string) file_get_contents($root.'/tests/Controller/AttachmentManagementTest.php');

        self::assertStringContainsString("#[Route('/admin/utenti')]", $userController);
        self::assertStringContainsString('final class UserController', $userController);
        self::assertStringContainsString('Non è possibile aggiungere documenti a una commessa archiviata.', $attachmentManager);
        self::assertStringContainsString('testArchivedProjectRejectsCraftedUpload', $attachmentTest);
    }
}
