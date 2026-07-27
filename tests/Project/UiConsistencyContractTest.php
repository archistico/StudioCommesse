<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;

final class UiConsistencyContractTest extends TestCase
{
    public function testAllApplicationTablesUseDataTablesAndNoGenericActionColumn(): void
    {
        $templateRoot = dirname(__DIR__, 2).'/templates';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templateRoot));
        $tableCount = 0;

        foreach ($iterator as $file) {
            if (!$file->isFile() || 'twig' !== $file->getExtension()) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            $tableCount += substr_count($contents, '<table ');
            if (str_contains($contents, '<table ')) {
                preg_match_all('/\bdata-datatable(?=[\s>])/', $contents, $matches);
                self::assertSame(substr_count($contents, '<table '), count($matches[0]), $file->getPathname());
            }
            self::assertStringNotContainsString('visually-hidden">Azioni', $contents, $file->getPathname());
            self::assertStringNotContainsString('<th>Azioni</th>', $contents, $file->getPathname());
            self::assertStringNotContainsString('<th>Apri</th>', $contents, $file->getPathname());
        }

        self::assertGreaterThan(10, $tableCount);
    }

    public function testLayoutsDoNotCreateSmallOrMediumScreenColumns(): void
    {
        $templateRoot = dirname(__DIR__, 2).'/templates';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templateRoot));

        foreach ($iterator as $file) {
            if (!$file->isFile() || 'twig' !== $file->getExtension()) {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            self::assertDoesNotMatchRegularExpression('/\\bcol-(?:sm|md)-\\d+\\b/', $contents, $file->getPathname());
        }
    }

    public function testLargeScreenColumnsAlwaysFallBackToFullWidth(): void
    {
        $templateRoot = dirname(__DIR__, 2).'/templates';
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($templateRoot));

        foreach ($iterator as $file) {
            if (!$file->isFile() || 'twig' !== $file->getExtension()) {
                continue;
            }
            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all('/class="([^"]*)"/', $contents, $matches);
            foreach ($matches[1] as $classList) {
                if (preg_match('/\bcol-(?:lg|xl)-(?:\d+|auto)\b/', $classList)) {
                    self::assertMatchesRegularExpression('/\bcol-12\b/', $classList, $file->getPathname().' :: '.$classList);
                }
            }
        }
    }

    public function testLocalizedDatesDurationsAndAmountsExposeMachineSortValues(): void
    {
        $expectations = [
            'templates/project/index.html.twig' => 'data-order="{{ project.dueDate',
            'templates/activity/index.html.twig' => 'data-order="{{ activity.initialEstimatedMinutes',
            'templates/time_entry/index.html.twig' => 'data-order="{{ entry.startedAt',
            'templates/time_entry/report.html.twig' => 'data-order="{{ entry.durationMinutes',
            'templates/economics/index.html.twig' => 'data-order="{{ summary.totalCostCents',
            'templates/economics/show.html.twig' => 'data-order="{{ expense.amountCents',
            'templates/control/index.html.twig' => 'data-order="{{ period.periodBalanceCents',
            'templates/control/collaborator.html.twig' => 'data-order="{{ entry.durationMinutes',
            'templates/user/index.html.twig' => 'data-order="{{ user.defaultHourlyRateCents',
        ];

        foreach ($expectations as $relativePath => $needle) {
            $contents = (string) file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
            self::assertStringContainsString($needle, $contents, $relativePath);
        }
    }

    public function testActivityMineFilterUsesAStringSafeValue(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2).'/src/Controller/ActivityController.php');
        $template = (string) file_get_contents(dirname(__DIR__, 2).'/templates/activity/index.html.twig');

        self::assertStringNotContainsString("getInt('assignee')", $controller);
        self::assertStringContainsString("get('assignee', 'me')", $controller);
        self::assertStringContainsString('<option value="me"', $template);
    }

    public function testDestructiveEconomicsActionsAreOnlyInEditForm(): void
    {
        $show = (string) file_get_contents(dirname(__DIR__, 2).'/templates/economics/show.html.twig');
        $form = (string) file_get_contents(dirname(__DIR__, 2).'/templates/economics/form.html.twig');

        self::assertStringNotContainsString("app_expense_delete", $show);
        self::assertStringNotContainsString("app_payment_delete", $show);
        self::assertStringContainsString("app_expense_delete", $form);
        self::assertStringContainsString("app_payment_delete", $form);
    }
    public function testPrimaryLinksReplaceGenericActionColumns(): void
    {
        $clientIndex = (string) file_get_contents(dirname(__DIR__, 2).'/templates/client/index.html.twig');
        $projectIndex = (string) file_get_contents(dirname(__DIR__, 2).'/templates/project/index.html.twig');
        $userIndex = (string) file_get_contents(dirname(__DIR__, 2).'/templates/user/index.html.twig');
        $activityIndex = (string) file_get_contents(dirname(__DIR__, 2).'/templates/activity/index.html.twig');
        $clientForm = (string) file_get_contents(dirname(__DIR__, 2).'/templates/client/form.html.twig');
        $projectForm = (string) file_get_contents(dirname(__DIR__, 2).'/templates/project/form.html.twig');

        self::assertStringContainsString("path('app_client_show'", $clientIndex);
        self::assertStringContainsString("path('app_project_show'", $projectIndex);
        self::assertStringContainsString("path('app_user_edit'", $userIndex);
        self::assertStringContainsString("path('app_activity_edit'", $activityIndex);
        foreach ([$clientIndex, $projectIndex, $userIndex, $activityIndex] as $template) {
            self::assertStringNotContainsString('<th>Azioni</th>', $template);
            self::assertStringNotContainsString('<th>Apri</th>', $template);
        }
        self::assertStringContainsString("path('app_client_archive'", $clientForm);
        self::assertStringContainsString("path('app_project_archive'", $projectForm);
    }

}
