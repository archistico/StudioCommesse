<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class PhpStanContractLineEndingHotfixTest extends TestCase
{
    public function testHotfix2ContractIsIndependentFromPlatformLineEndings(): void
    {
        $root = dirname(__DIR__, 2);
        $contract = (string) file_get_contents($root.'/scripts/m92c-hotfix2-phpstan-contract.php');
        $subscriber = str_replace("\r\n", "\n", (string) file_get_contents($root.'/src/EventSubscriber/DatabaseExceptionSubscriber.php'));
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');

        self::assertStringContainsString('str_replace("\r\n", "\n", $content)', $contract);
        self::assertStringContainsString("preg_match('/}\\s*else\\s*{\\s*return;\\s*}/s'", $contract);
        self::assertSame(1, preg_match('/}\s*else\s*{\s*return;\s*}/s', $subscriber));
        self::assertStringContainsString('scripts/m92c-hotfix3-contract.php', $validation);
        self::assertStringContainsString('M9.2-H VALIDATION PASSED', $validation);
    }
}
