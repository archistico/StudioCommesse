<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class EndToEndWorkflowContractTest extends TestCase
{
    public function testEndToEndScenariosArePackagedAndPartOfTheValidationGate(): void
    {
        $root = dirname(__DIR__, 2);
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');
        $packager = (string) file_get_contents($root.'/scripts/package-release.ps1');
        $verifier = (string) file_get_contents($root.'/scripts/verify-release-package.ps1');

        foreach ([
            'tests/Controller/EndToEndWorkflowTest.php',
            'tests/Service/BackupManagerTest.php',
            'tests/Project/EndToEndWorkflowContractTest.php',
            'scripts/m92e-end-to-end-contract.php',
            'docs/END_TO_END_FLOWS.md',
        ] as $file) {
            self::assertFileExists($root.'/'.$file);
            self::assertStringContainsString("'{$file}'", $packager, $file);
            self::assertStringContainsString("'{$file}'", $verifier, $file);
        }

        self::assertStringContainsString('scripts/m92e-end-to-end-contract.php', $validation);
        self::assertStringContainsString('M9.2-H VALIDATION PASSED', $validation);
        self::assertStringContainsString('StudioCommesse_M9.2-H_Accessibility_Security_Manuals_Fix1.zip', $packager);

        $workflow = (string) file_get_contents($root.'/tests/Controller/EndToEndWorkflowTest.php');
        self::assertStringContainsString('$this->entityManager->find(Project::class, $projectId)', $workflow);
        self::assertStringContainsString('$this->entityManager->find(Client::class, $clientId)', $workflow);
        self::assertStringNotContainsString('$this->entityManager->refresh($project)', $workflow);
        self::assertStringNotContainsString('$this->entityManager->refresh($client)', $workflow);
    }
}
