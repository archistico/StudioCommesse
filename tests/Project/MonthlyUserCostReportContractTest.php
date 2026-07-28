<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class MonthlyUserCostReportContractTest extends TestCase
{
    public function testMonthlyUserCostSummaryKeepsCurrentStandardAndHistoricalSnapshotSeparate(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/src/Controller/MonthlyReportController.php');
        $repository = (string) file_get_contents($root.'/src/Repository/MonthlyReportRepository.php');
        $service = (string) file_get_contents($root.'/src/Service/MonthlyReportService.php');
        $template = (string) file_get_contents($root.'/templates/report/monthly.html.twig');
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');
        $packager = (string) file_get_contents($root.'/scripts/package-release.ps1');
        $verifier = (string) file_get_contents($root.'/scripts/verify-release-package.ps1');

        self::assertStringContainsString("name: 'app_monthly_report_users_csv'", $controller);
        self::assertStringContainsString('findUserCostSummaries', $repository);
        self::assertStringContainsString('time_entry.ended_at IS NOT NULL', $repository);
        self::assertStringContainsString('worker.default_hourly_rate_cents', $repository);
        self::assertStringContainsString('SUM(time_entry.cost_snapshot_cents)', $repository);
        self::assertStringContainsString('$workedMinutes * $standardHourlyRateCents / 60', $service);
        self::assertStringContainsString('Costo standard teorico', $template);
        self::assertStringContainsString('Costo storico effettivo', $template);
        self::assertStringContainsString('Non impostata', $template);
        self::assertStringContainsString('M9.2-H VALIDATION PASSED', $validation);
        foreach ([
            'src/Query/MonthlyUserCostReportRow.php',
            'tests/Project/MonthlyUserCostReportContractTest.php',
            'scripts/m92e1-monthly-user-cost-contract.php',
            'docs/MONTHLY_REPORT.md',
        ] as $required) {
            self::assertStringContainsString("'{$required}'", $packager, $required);
            self::assertStringContainsString("'{$required}'", $verifier, $required);
        }
    }
}
