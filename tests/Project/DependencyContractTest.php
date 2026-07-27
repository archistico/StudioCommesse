<?php

declare(strict_types=1);

namespace App\Tests\Project;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class DependencyContractTest extends TestCase
{
    public function testSymfonyAndMonologConstraintsAreCompatible(): void
    {
        $composer = $this->readJson('composer.json');
        $requirements = $composer['require'] ?? null;

        self::assertIsArray($requirements);
        self::assertSame('8.1.*', $requirements['symfony/framework-bundle'] ?? null);
        self::assertSame('^4.0.2', $requirements['symfony/monolog-bundle'] ?? null);
    }

    public function testTablerVersionIsPinned(): void
    {
        $package = $this->readJson('package.json');
        $developmentDependencies = $package['devDependencies'] ?? null;

        self::assertIsArray($developmentDependencies);
        self::assertSame('1.4.0', $developmentDependencies['@tabler/core'] ?? null);
        self::assertSame('2.3.8', $developmentDependencies['datatables.net-bs5'] ?? null);
        self::assertSame('3.0.8', $developmentDependencies['datatables.net-responsive-bs5'] ?? null);
        self::assertSame('3.6.4', $developmentDependencies['jquery'] ?? null);
    }

    public function testTablerLicenseNoticeIsStoredInProject(): void
    {
        $noticePath = dirname(__DIR__, 2).'/THIRD_PARTY_NOTICES.md';
        self::assertFileExists($noticePath);

        $notice = file_get_contents($noticePath);
        self::assertIsString($notice);
        self::assertStringContainsString('Tabler 1.4.0', $notice);
        self::assertStringContainsString('DataTables 2.3.8', $notice);
        self::assertStringContainsString('Responsive 3.0.8', $notice);
        self::assertStringContainsString('jQuery 3.6.4', $notice);
        self::assertStringContainsString('MIT License', $notice);
    }

    public function testAssetInstallerDoesNotExpectLicenseInsideNpmPackage(): void
    {
        $scriptPath = dirname(__DIR__, 2).'/scripts/copy-tabler-assets.mjs';
        $script = file_get_contents($scriptPath);

        self::assertIsString($script);
        self::assertStringNotContainsString("['LICENSE', 'LICENSE']", $script);
        self::assertStringContainsString("'NOTICE.txt'", $script);
        self::assertStringContainsString("'datatables.net-bs5'", $script);
        self::assertStringContainsString("'datatables.net-responsive-bs5'", $script);
        self::assertStringContainsString("'jquery'", $script);
    }


    public function testPowerShellScriptsAvoidAmbiguousVariableColonInterpolation(): void
    {
        foreach (['scripts/setup.ps1', 'scripts/validate.ps1'] as $relativePath) {
            $script = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);

            self::assertIsString($script);
            self::assertStringNotContainsString('$exitCode:', $script);
            self::assertStringContainsString('"Comando fallito con codice {0}: {1}" -f', $script);
        }
    }

    public function testPowerShellScriptsValidateAllScriptSyntax(): void
    {
        foreach (['scripts/setup.ps1', 'scripts/validate.ps1'] as $relativePath) {
            $script = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);

            self::assertIsString($script);
            self::assertStringContainsString('Assert-PowerShellScriptSyntax', $script);
            self::assertStringContainsString('[System.Management.Automation.Language.Parser]::ParseFile', $script);
        }
    }


    public function testDoctrineConfigurationOmitsRemovedDbalAndOrmOptions(): void
    {
        $configuration = Yaml::parseFile(dirname(__DIR__, 2).'/config/packages/doctrine.yaml');
        self::assertIsArray($configuration);

        $doctrine = $configuration['doctrine'] ?? null;
        self::assertIsArray($doctrine);

        $dbal = $doctrine['dbal'] ?? null;
        self::assertIsArray($dbal);
        self::assertArrayNotHasKey('use_savepoints', $dbal);
        self::assertSame('%env(resolve:DATABASE_URL)%', $dbal['url'] ?? null);
        self::assertFalse($dbal['profiling_collect_backtrace'] ?? true);
        self::assertArrayNotHasKey('schema_filter', $dbal);

        $developmentDbal = $configuration['when@dev']['doctrine']['dbal'] ?? null;
        self::assertIsArray($developmentDbal);
        self::assertFalse($developmentDbal['logging'] ?? true);
        self::assertFalse($developmentDbal['profiling'] ?? true);

        $orm = $doctrine['orm'] ?? null;
        self::assertIsArray($orm);
        foreach (['auto_generate_proxy_classes', 'proxy_dir', 'proxy_namespace', 'enable_lazy_ghost_objects'] as $removedKey) {
            self::assertArrayNotHasKey($removedKey, $orm);
        }
        self::assertTrue($orm['auto_mapping'] ?? false);

        $productionOrm = $configuration['when@prod']['doctrine']['orm'] ?? null;
        self::assertIsArray($productionOrm);
        foreach (['auto_generate_proxy_classes', 'proxy_dir', 'proxy_namespace', 'enable_lazy_ghost_objects'] as $removedKey) {
            self::assertArrayNotHasKey($removedKey, $productionOrm);
        }
    }

    public function testMigrationMetadataTableRemainsVisibleToMigrationCommands(): void
    {
        $configuration = Yaml::parseFile(dirname(__DIR__, 2).'/config/packages/doctrine.yaml');
        self::assertIsArray($configuration);

        self::assertArrayNotHasKey('schema_filter', $configuration['doctrine']['dbal']);
    }

    public function testUsernameUniqueConstraintMatchesInitialMigration(): void
    {
        $entity = file_get_contents(dirname(__DIR__, 2).'/src/Entity/User.php');
        $migration = file_get_contents(dirname(__DIR__, 2).'/migrations/Version20260727090000.php');

        self::assertIsString($entity);
        self::assertIsString($migration);
        self::assertStringContainsString("#[ORM\\UniqueConstraint(name: 'uniq_app_user_username', columns: ['username'])]", $entity);
        self::assertStringNotContainsString('unique: true', $entity);
        self::assertStringContainsString("addUniqueIndex(['username'], 'uniq_app_user_username')", $migration);
    }

    public function testValidationPrintsSchemaDriftSqlBeforeFailing(): void
    {
        $validation = file_get_contents(dirname(__DIR__, 2).'/scripts/validate.ps1');

        self::assertIsString($validation);
        self::assertStringContainsString('function Invoke-SchemaValidation', $validation);
        self::assertStringContainsString('doctrine:schema:update --dump-sql --env=test', $validation);
        self::assertStringContainsString('Invoke-SchemaValidation', $validation);
    }

    public function testDoctrinePreflightContractRunsBeforeSymfonyConfigurationChecks(): void
    {
        foreach (['scripts/setup.ps1', 'scripts/validate.ps1'] as $relativePath) {
            $script = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
            self::assertIsString($script);

            $contractPosition = strpos($script, '@("scripts/doctrine-config-contract.php")');
            $yamlLintPosition = strpos($script, '@("bin/console", "lint:yaml", "config")');
            self::assertIsInt($contractPosition);
            self::assertIsInt($yamlLintPosition);
            self::assertLessThan($yamlLintPosition, $contractPosition);
            self::assertStringNotContainsString('debug:config', $script);
        }
    }

    public function testPowerShellInstallationDefersComposerScriptsAndRunsSymfonyChecksExplicitly(): void
    {
        $setup = file_get_contents(dirname(__DIR__, 2).'/scripts/setup.ps1');
        self::assertIsString($setup);

        self::assertStringContainsString('"--no-scripts"', $setup);
        self::assertStringContainsString('@("bin/console", "lint:yaml", "config")', $setup);
        self::assertStringContainsString('@("bin/console", "lint:twig", "templates")', $setup);
        self::assertStringContainsString('@("bin/console", "cache:clear", "--env=dev")', $setup);
    }

    public function testSymfonyApiPreflightRunsBeforeKernelBootstrap(): void
    {
        foreach (['scripts/setup.ps1', 'scripts/validate.ps1'] as $relativePath) {
            $script = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
            self::assertIsString($script);

            $contractPosition = strpos($script, '@("scripts/symfony-api-contract.php")');
            $yamlLintPosition = strpos($script, '@("bin/console", "lint:yaml", "config")');

            self::assertIsInt($contractPosition);
            self::assertIsInt($yamlLintPosition);
            self::assertLessThan($yamlLintPosition, $contractPosition);
        }
    }


    public function testHistoricalM3MigrationsRemainPresent(): void
    {
        $services = file_get_contents(dirname(__DIR__, 2).'/config/services.yaml');
        $package = $this->readJson('package.json');
        $m2Migration = file_get_contents(dirname(__DIR__, 2).'/migrations/Version20260727120000.php');
        $m3Migration = file_get_contents(dirname(__DIR__, 2).'/migrations/Version20260727170000.php');

        self::assertIsString($services);
        self::assertIsString($m2Migration);
        self::assertIsString($m3Migration);
        self::assertStringContainsString("app.version: '0.9.2-M9.2-A-HF1'", $services);
        self::assertSame('0.9.2-m9.2-a-hf1', $package['version'] ?? null);
        self::assertStringContainsString("createTable('client')", $m2Migration);
        self::assertStringContainsString("createTable('project')", $m2Migration);
        self::assertStringContainsString("createTable('project_code_sequence')", $m2Migration);
        self::assertStringContainsString("createTable('activity')", $m3Migration);
    }

    public function testProjectCodeYearAvoidsSqliteImplicitAutoincrementDrift(): void
    {
        $entity = file_get_contents(dirname(__DIR__, 2).'/src/Entity/ProjectCodeSequence.php');
        $migration = file_get_contents(dirname(__DIR__, 2).'/migrations/Version20260727143000.php');

        self::assertIsString($entity);
        self::assertIsString($migration);
        self::assertStringContainsString("type: Types::SMALLINT", $entity);
        self::assertStringContainsString('year_value SMALLINT NOT NULL', $migration);
    }

    public function testPriorityDoesNotDuplicateUrgency(): void
    {
        $project = file_get_contents(dirname(__DIR__, 2).'/src/Entity/Project.php');
        $priority = file_get_contents(dirname(__DIR__, 2).'/src/Enum/ProjectPriority.php');

        self::assertIsString($project);
        self::assertIsString($priority);
        self::assertStringContainsString("case Urgent = 'urgent';", $priority);
        self::assertStringNotContainsString('private bool $urgent', $project);
        self::assertStringNotContainsString('isUrgent()', $project);
    }

    public function testM4ValidationGateIsNamedExplicitly(): void
    {
        $validation = file_get_contents(dirname(__DIR__, 2).'/scripts/validate.ps1');

        self::assertIsString($validation);
        self::assertStringContainsString('M9.2-A HOTFIX 1 VALIDATION PASSED', $validation);
        self::assertStringNotContainsString('M2 HOTFIX 1 VALIDATION PASSED', $validation);
        self::assertStringNotContainsString('M1 HOTFIX 7 VALIDATION PASSED', $validation);
    }


    public function testSetupUsesGuidedPartnerBootstrapWithoutDefaultCredentials(): void
    {
        $setup = file_get_contents(dirname(__DIR__, 2).'/scripts/setup.ps1');
        $setupSh = file_get_contents(dirname(__DIR__, 2).'/scripts/setup.sh');
        $command = file_get_contents(dirname(__DIR__, 2).'/src/Command/CreateUserCommand.php');

        self::assertIsString($setup);
        self::assertIsString($setupSh);
        self::assertIsString($command);
        self::assertStringContainsString('--skip-if-active-partner-exists', $setup);
        self::assertStringContainsString('--skip-if-active-partner-exists', $setupSh);
        self::assertStringContainsString('[switch]$SkipPartnerBootstrap', $setup);
        self::assertStringContainsString("InputArgument::OPTIONAL, 'Nome utente'", $command);
        self::assertStringContainsString("askHidden('Password (almeno 12 caratteri)')", $command);
        self::assertStringNotContainsString('Password-sicura-123!', $setup);
    }

    public function testUnsupportedDebugConfigProbeIsAbsentFromInstallationAndValidation(): void
    {
        foreach (['scripts/setup.ps1', 'scripts/validate.ps1', 'scripts/setup.sh'] as $relativePath) {
            $script = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
            self::assertIsString($script);
            self::assertStringNotContainsString('debug:config', $script);
        }
    }


    public function testM4TimeTrackingContractsAreAligned(): void
    {
        $services = file_get_contents(dirname(__DIR__, 2).'/config/services.yaml');
        $package = $this->readJson('package.json');
        $migration = file_get_contents(dirname(__DIR__, 2).'/migrations/Version20260727200000.php');
        $validation = file_get_contents(dirname(__DIR__, 2).'/scripts/validate.ps1');
        self::assertIsString($services); self::assertIsString($migration); self::assertIsString($validation);
        self::assertStringContainsString("app.version: '0.9.2-M9.2-A-HF1'", $services);
        self::assertSame('0.9.2-m9.2-a-hf1', $package['version'] ?? null);
        self::assertStringContainsString("createTable('time_entry')", $migration);
        self::assertStringContainsString('M9.2-A HOTFIX 1 VALIDATION PASSED', $validation);
        self::assertStringNotContainsString('M3 HOTFIX 1 VALIDATION PASSED', $validation);
    }

    public function testM4UsesCalculatedDurationWithoutDuplicatedDurationColumn(): void
    {
        $entity = file_get_contents(dirname(__DIR__, 2).'/src/Entity/TimeEntry.php');
        $migration = file_get_contents(dirname(__DIR__, 2).'/migrations/Version20260727200000.php');
        self::assertIsString($entity); self::assertIsString($migration);
        self::assertStringContainsString('getDurationMinutes', $entity);
        self::assertStringNotContainsString("addColumn('duration", $migration);
    }

    public function testM4Hotfix1FixesTimeEntryFormGenericAndActivityMenuIcon(): void
    {
        $form = file_get_contents(dirname(__DIR__, 2).'/src/Form/TimeEntryType.php');
        $layout = file_get_contents(dirname(__DIR__, 2).'/templates/layout/app.html.twig');

        self::assertIsString($form);
        self::assertIsString($layout);
        self::assertStringContainsString('/** @extends AbstractType<TimeEntry> */', $form);
        self::assertStringContainsString("starts with 'app_activity_'", $layout);
        self::assertStringContainsString('nav-link-title">Attività', $layout);
        self::assertStringContainsString('nav-link-icon d-md-none d-lg-inline-block', $layout);
    }

    public function testM5Hotfix1ChoiceCatalogsDoNotUseRedundantTernaryFallbacks(): void
    {
        $expenseForm = file_get_contents(dirname(__DIR__, 2).'/src/Form/ExpenseType.php');
        $paymentForm = file_get_contents(dirname(__DIR__, 2).'/src/Form/PaymentType.php');

        self::assertIsString($expenseForm);
        self::assertIsString($paymentForm);
        self::assertStringContainsString('array_combine(Expense::CATEGORIES, Expense::CATEGORIES)', $expenseForm);
        self::assertStringContainsString('array_combine(Payment::METHODS, Payment::METHODS)', $paymentForm);
        self::assertStringNotContainsString('Expense::CATEGORIES) ?: []', $expenseForm);
        self::assertStringNotContainsString('Payment::METHODS) ?: []', $paymentForm);
    }

    public function testM5Hotfix3RendersFinancialProjectFieldsBeforeSubmit(): void
    {
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/project/form.html.twig');

        self::assertIsString($template);
        $estimatedPosition = strpos($template, 'form.estimatedAmountCents');
        $ratePosition = strpos($template, 'form.defaultHourlyRateCents');
        $submitPosition = strpos($template, 'type="submit"');
        self::assertIsInt($estimatedPosition);
        self::assertIsInt($ratePosition);
        self::assertIsInt($submitPosition);
        self::assertLessThan($submitPosition, $estimatedPosition);
        self::assertLessThan($submitPosition, $ratePosition);
        self::assertStringContainsString('form_rest(form)', $template);
        self::assertStringContainsString('form_end(form, {render_rest: false})', $template);
    }

    public function testM5Hotfix3UsesBulkAggregatesAndLowOverheadDevelopmentConfiguration(): void
    {
        $timeEntries = file_get_contents(dirname(__DIR__, 2).'/src/Repository/TimeEntryRepository.php');
        $financialService = file_get_contents(dirname(__DIR__, 2).'/src/Service/ProjectFinancialService.php');
        $projectController = file_get_contents(dirname(__DIR__, 2).'/src/Controller/ProjectController.php');
        $activityController = file_get_contents(dirname(__DIR__, 2).'/src/Controller/ActivityController.php');
        $diagnostics = file_get_contents(dirname(__DIR__, 2).'/scripts/diagnose-performance.ps1');
        $server = file_get_contents(dirname(__DIR__, 2).'/scripts/start-server.ps1');

        foreach ([$timeEntries, $financialService, $projectController, $activityController, $diagnostics, $server] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString('sumMinutesByActivityIds', $timeEntries);
        self::assertStringContainsString('sumCostCentsByProjectIds', $timeEntries);
        self::assertStringContainsString('summarizeMinutesByActivityAndUserIds($activityIds)', $projectController);
        self::assertStringContainsString('sumMinutesByActivityIds($activityIds)', $activityController);
        self::assertStringContainsString('sumCentsByProjectIds($projectIds)', $financialService);
        self::assertStringNotContainsString('array_map(fn (Project $project): ProjectFinancialSummary => $this->summarize($project)', $financialService);
        self::assertStringContainsString('opcache.enable_cli', $diagnostics);
        self::assertStringContainsString('/economia', $diagnostics);
        self::assertStringContainsString('[ValidateSet("dev", "fast")]', $server);
    }


    public function testM62Hotfix1AddsPartnerOnlyDailyCollaboratorEvaluation(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/src/Controller/CollaboratorEvaluationController.php');
        $repository = file_get_contents(dirname(__DIR__, 2).'/src/Repository/ControlRepository.php');
        $service = file_get_contents(dirname(__DIR__, 2).'/src/Service/CollaboratorEvaluationService.php');
        $template = file_get_contents(dirname(__DIR__, 2).'/templates/control/collaborator.html.twig');
        $controlTemplate = file_get_contents(dirname(__DIR__, 2).'/templates/control/index.html.twig');

        foreach ([$controller, $repository, $service, $template, $controlTemplate] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString("#[Route('/controllo/collaboratori')]", $controller);
        self::assertStringContainsString("#[IsGranted('ROLE_PARTNER')]", $controller);
        self::assertStringContainsString('findCollaboratorWorkEntries', $repository);
        self::assertStringContainsString('$dayKey = $startedAt->format(\'Y-m-d\')', $service);
        self::assertStringContainsString('Valutazione collaboratore', $template);
        self::assertStringContainsString('Lavoro svolto', $template);
        self::assertStringContainsString('Dettaglio giornaliero', $controlTemplate);
        self::assertCount(8, glob(dirname(__DIR__, 2).'/migrations/Version*.php') ?: []);
    }

    /** @return array<string, mixed> */
    private function readJson(string $relativePath): array
    {
        $path = dirname(__DIR__, 2).'/'.$relativePath;
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        $decoded = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    public function testM5Hotfix2RestoresProjectArchiveAndRestoreControls(): void
    {
        $show = file_get_contents(dirname(__DIR__, 2).'/templates/project/show.html.twig');
        $form = file_get_contents(dirname(__DIR__, 2).'/templates/project/form.html.twig');

        self::assertIsString($show);
        self::assertIsString($form);
        self::assertStringNotContainsString("path('app_project_archive'", $show);
        self::assertStringContainsString("path('app_project_archive'", $form);
        self::assertStringContainsString("csrf_token('archive_project_' ~ project.id)", $form);
        self::assertStringContainsString('>Archivia commessa</button>', $form);
        self::assertStringContainsString("path('app_project_restore'", $show);
        self::assertStringContainsString("csrf_token('restore_project_' ~ project.id)", $show);
        self::assertStringContainsString('>Ripristina</button>', $show);
        $controller = file_get_contents(dirname(__DIR__, 2).'/src/Controller/ProjectController.php');
        self::assertIsString($controller);
        self::assertStringContainsString("#[IsGranted('ROLE_PARTNER')]", $controller);
    }

    public function testM5EconomicsAndLayoutContractsAreAligned(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/migrations/Version20260727223000.php');
        $project = file_get_contents(dirname(__DIR__, 2).'/src/Entity/Project.php');
        $show = file_get_contents(dirname(__DIR__, 2).'/templates/project/show.html.twig');
        $dashboard = file_get_contents(dirname(__DIR__, 2).'/templates/dashboard/index.html.twig');
        $duration = file_get_contents(dirname(__DIR__, 2).'/src/Twig/DurationExtension.php');
        self::assertIsString($migration); self::assertIsString($project); self::assertIsString($show); self::assertIsString($dashboard); self::assertIsString($duration);
        self::assertStringContainsString('CREATE TABLE expense', $migration);
        self::assertStringContainsString('CREATE TABLE payment', $migration);
        self::assertStringContainsString('estimatedAmountCents', $project);
        self::assertStringContainsString('col-lg-6 d-flex', $show);
        self::assertStringContainsString('activity_time', $show);
        self::assertStringContainsString('col-xl-3', $dashboard);
        self::assertStringNotContainsString('Milestone M2', $dashboard);
        self::assertStringContainsString("new TwigFilter('duration_hm'", $duration);
    }

    public function testM5VersionMigrationAndValidationGateAreAligned(): void
    {
        $services = file_get_contents(dirname(__DIR__, 2).'/config/services.yaml');
        $package = $this->readJson('package.json');
        $migration = file_get_contents(dirname(__DIR__, 2).'/migrations/Version20260727233000.php');
        $validation = file_get_contents(dirname(__DIR__, 2).'/scripts/validate.ps1');

        self::assertIsString($services);
        self::assertIsString($migration);
        self::assertIsString($validation);
        self::assertStringContainsString("app.version: '0.9.2-M9.2-A-HF1'", $services);
        self::assertSame('0.9.2-m9.2-a-hf1', $package['version'] ?? null);
        self::assertStringContainsString('ALTER TABLE payment ADD COLUMN description', $migration);
        self::assertStringContainsString('hourly_rate_snapshot_cents', $migration);
        self::assertStringContainsString('M9.2-A HOTFIX 1 VALIDATION PASSED', $validation);
        self::assertStringNotContainsString('M4 HOTFIX 1 VALIDATION PASSED', $validation);
    }

    public function testRequestedDashboardAndProjectDetailLayoutArePresent(): void
    {
        $dashboard = file_get_contents(dirname(__DIR__, 2).'/templates/dashboard/index.html.twig');
        $project = file_get_contents(dirname(__DIR__, 2).'/templates/project/show.html.twig');

        self::assertIsString($dashboard);
        self::assertIsString($project);
        self::assertStringContainsString('col-12 col-lg-6 col-xl-3', $dashboard);
        self::assertStringContainsString('Utenti attivi', $dashboard);
        self::assertStringNotContainsString('Milestone', $dashboard);
        self::assertStringContainsString('col-lg-6 d-flex flex-column gap-3', $project);
        self::assertStringContainsString('col-lg-6 d-flex', $project);
        self::assertLessThan(strpos($project, 'Nota riservata'), strpos($project, 'Riferimenti'));
        self::assertStringContainsString('activity_time[activity.id]', $project);
        self::assertStringContainsString('|duration_hm', $project);
    }

    public function testFinancialDataIsProtectedAndMoneyIsCentralized(): void
    {
        $projectForm = file_get_contents(dirname(__DIR__, 2).'/src/Form/ProjectType.php');
        $activityForm = file_get_contents(dirname(__DIR__, 2).'/src/Form/ActivityType.php');
        $timeEntries = file_get_contents(dirname(__DIR__, 2).'/templates/time_entry/index.html.twig');
        $voter = file_get_contents(dirname(__DIR__, 2).'/src/Security/Voter/ProjectVoter.php');
        $money = file_get_contents(dirname(__DIR__, 2).'/src/Twig/MoneyExtension.php');

        self::assertIsString($projectForm);
        self::assertIsString($activityForm);
        self::assertIsString($timeEntries);
        self::assertIsString($voter);
        self::assertIsString($money);
        self::assertStringContainsString('if ($allowAdministration)', $projectForm);
        self::assertStringContainsString("'allow_financial'", $activityForm);
        self::assertStringContainsString("PROJECT_VIEW_FINANCIAL", $voter);
        self::assertStringContainsString("is_granted('PROJECT_VIEW_FINANCIAL', activity.project)", $timeEntries);
        self::assertStringContainsString("new TwigFilter('money_eur'", $money);
    }

    public function testRateSnapshotAndEconomicEntitiesAreImplemented(): void
    {
        $user = file_get_contents(dirname(__DIR__, 2).'/src/Entity/User.php');
        $project = file_get_contents(dirname(__DIR__, 2).'/src/Entity/Project.php');
        $activity = file_get_contents(dirname(__DIR__, 2).'/src/Entity/Activity.php');
        $entry = file_get_contents(dirname(__DIR__, 2).'/src/Entity/TimeEntry.php');
        $resolver = file_get_contents(dirname(__DIR__, 2).'/src/Service/HourlyRateResolver.php');
        $expense = file_get_contents(dirname(__DIR__, 2).'/src/Entity/Expense.php');
        $payment = file_get_contents(dirname(__DIR__, 2).'/src/Entity/Payment.php');

        foreach ([$user, $project, $activity, $entry, $resolver, $expense, $payment] as $source) {
            self::assertIsString($source);
        }
        self::assertStringContainsString('defaultHourlyRateCents', $user);
        self::assertStringContainsString('defaultHourlyRateCents', $project);
        self::assertStringContainsString('hourlyRateOverrideCents', $activity);
        self::assertStringContainsString('applyRateSnapshot', $entry);
        $timer = file_get_contents(dirname(__DIR__, 2).'/src/Service/TimerService.php');
        self::assertIsString($timer);
        self::assertStringContainsString('->applyRateSnapshot($this->hourlyRateResolver->resolve($activity, $user))', $timer);
        self::assertStringContainsString('$entry->recalculateCostFromSnapshot();', $timer);
        self::assertStringContainsString('activityRate', $resolver);
        self::assertStringContainsString('reimbursable', $expense);
        self::assertStringContainsString('description', $payment);
    }

    public function testM61HoursReportingContractsArePresent(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/src/Controller/TimeEntryController.php');
        $repository = file_get_contents(dirname(__DIR__, 2).'/src/Repository/TimeEntryRepository.php');
        $report = file_get_contents(dirname(__DIR__, 2).'/templates/time_entry/report.html.twig');
        $project = file_get_contents(dirname(__DIR__, 2).'/templates/project/show.html.twig');
        $activities = file_get_contents(dirname(__DIR__, 2).'/templates/activity/index.html.twig');
        $layout = file_get_contents(dirname(__DIR__, 2).'/templates/layout/app.html.twig');

        foreach ([$controller, $repository, $report, $project, $activities, $layout] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString("name: 'app_time_entry_index'", $controller);
        self::assertStringContainsString('new TimeEntrySearchCriteria(', $controller);
        self::assertStringContainsString('findPage(TimeEntrySearchCriteria $criteria)', $repository);
        self::assertStringContainsString('summarizeMinutesByActivityAndUserIds', $repository);
        self::assertStringContainsString('Consuntivato totale:', $project);
        self::assertStringContainsString('time_summary.contributors', $project);
        self::assertStringContainsString('>Assegnatario</label>', $activities);
        self::assertStringContainsString('Le mie attività', $activities);
        self::assertStringContainsString('Persona che ha lavorato', $report);
        self::assertStringContainsString('page.totalPages', $report);
        self::assertStringNotContainsString('hourlyRateSnapshotCents', $report);
        self::assertStringNotContainsString('costSnapshotCents', $report);
        self::assertStringContainsString('nav-link-title">Ore', $layout);
    }

    public function testM62ControlAndClosureContractsArePresent(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/src/Controller/ControlController.php');
        $service = file_get_contents(dirname(__DIR__, 2).'/src/Service/ProjectControlService.php');
        $repository = file_get_contents(dirname(__DIR__, 2).'/src/Repository/ControlRepository.php');
        $control = file_get_contents(dirname(__DIR__, 2).'/templates/control/index.html.twig');
        $project = file_get_contents(dirname(__DIR__, 2).'/templates/project/show.html.twig');
        $layout = file_get_contents(dirname(__DIR__, 2).'/templates/layout/app.html.twig');

        foreach ([$controller, $service, $repository, $control, $project, $layout] as $source) {
            self::assertIsString($source);
        }

        self::assertStringContainsString("#[IsGranted('ROLE_PARTNER')]", $controller);
        self::assertStringContainsString("name: 'app_control_index'", $controller);
        self::assertStringContainsString('studio_commesse.control_filters', $controller);
        self::assertStringContainsString('STALLED_AFTER_DAYS = 14', $service);
        self::assertStringContainsString('OVERLOAD_ACTIVITY_COUNT = 8', $service);
        self::assertMatchesRegularExpression('/OVERLOAD_REMAINING_MINUTES\s*=\s*2_?400/', $service);
        self::assertStringContainsString('project.archived_at IS NULL', $repository);
        self::assertStringContainsString('Chiusura operativa', $control);
        self::assertStringContainsString('Carico per persona', $control);
        self::assertStringContainsString('Filtri persistenti', $control);
        self::assertStringContainsString('Controllo chiusura', $project);
        self::assertStringContainsString('nav-link-title">Controllo', $layout);
        self::assertFileDoesNotExist(dirname(__DIR__, 2).'/migrations/Version20260728000000.php');
    }



    public function testM62Hotfix3KeepsPhpstanNarrowingExplicit(): void
    {
        $evaluationController = file_get_contents(dirname(__DIR__, 2).'/src/Controller/CollaboratorEvaluationController.php');
        $controlController = file_get_contents(dirname(__DIR__, 2).'/src/Controller/ControlController.php');
        $phpstan = file_get_contents(dirname(__DIR__, 2).'/phpstan.neon');

        self::assertIsString($evaluationController);
        self::assertIsString($controlController);
        self::assertIsString($phpstan);
        $normalizedEvaluationController = str_replace(["\r\n", "\r"], "\n", $evaluationController);
        $normalizedControlController = str_replace(["\r\n", "\r"], "\n", $controlController);

        self::assertStringContainsString("if (\$selectedProject instanceof Project\n            && \$selectedResponsible instanceof User", $normalizedEvaluationController);
        self::assertStringContainsString("], static fn (mixed \$value): bool => null !== \$value);", $normalizedEvaluationController);
        self::assertStringContainsString("\$values[\$key] = \$request->query->get(\$key, '');", $normalizedControlController);
        self::assertStringNotContainsString("is_scalar(\$value) ? (string) \$value : '';\n            }\n            \$session->set", $normalizedControlController);
        self::assertStringContainsString('level: 8', $phpstan);
        self::assertStringNotContainsString('treatPhpDocTypesAsCertain: false', $phpstan);
    }

    public function testM63DataTablesAssetsAndRuntimeAreWiredLocally(): void
    {
        $base = file_get_contents(dirname(__DIR__, 2).'/templates/base.html.twig');
        $runtime = file_get_contents(dirname(__DIR__, 2).'/public/assets/js/app.js');
        $assetCheck = file_get_contents(dirname(__DIR__, 2).'/scripts/check-assets.mjs');
        $lock = $this->readJson('package-lock.json');

        self::assertIsString($base);
        self::assertIsString($runtime);
        self::assertIsString($assetCheck);
        self::assertStringContainsString("vendor/datatables/css/dataTables.bootstrap5.min.css", $base);
        self::assertStringContainsString("vendor/datatables/css/responsive.bootstrap5.min.css", $base);
        self::assertStringContainsString("vendor/datatables/js/dataTables.min.js", $base);
        self::assertStringContainsString("vendor/datatables/js/dataTables.responsive.min.js", $base);
        self::assertStringNotContainsString('cdn.datatables.net', $base);
        self::assertStringContainsString('new window.DataTable(table, options)', $runtime);
        self::assertStringContainsString('responsive: true', $runtime);
        self::assertStringContainsString('searching: true', $runtime);
        self::assertStringContainsString("paging: !compact && !serverPage", $runtime);
        self::assertStringContainsString('public/vendor/datatables/js/dataTables.min.js', $assetCheck);
        self::assertSame('2.3.8', $lock['packages']['node_modules/datatables.net-bs5']['version'] ?? null);
        self::assertSame('3.0.8', $lock['packages']['node_modules/datatables.net-responsive-bs5']['version'] ?? null);
    }


    public function testM63Hotfix1KeepsArchiveCommandsInEditPagesAndMineFilterSemantic(): void
    {
        $clientForm = file_get_contents(dirname(__DIR__, 2).'/templates/client/form.html.twig');
        $projectForm = file_get_contents(dirname(__DIR__, 2).'/templates/project/form.html.twig');
        $reportingTest = file_get_contents(dirname(__DIR__, 2).'/tests/Controller/TimeEntryReportingTest.php');

        self::assertIsString($clientForm);
        self::assertIsString($projectForm);
        self::assertIsString($reportingTest);
        self::assertStringContainsString('{% if client is defined %}', $clientForm);
        self::assertStringContainsString('Archivia cliente', $clientForm);
        self::assertStringContainsString("{% if project is defined and is_granted('ROLE_PARTNER') %}", $projectForm);
        self::assertStringContainsString('Archivia commessa', $projectForm);
        self::assertStringContainsString("self::assertSame('me', \$form->get('assignee')->getValue());", $reportingTest);
        self::assertStringNotContainsString('option[value="me"][selected]', $reportingTest);
    }


    public function testM7ProtectedAttachmentContractsArePresent(): void
    {
        $root = dirname(__DIR__, 2);
        $entity = (string) file_get_contents($root.'/src/Entity/Attachment.php');
        $storage = (string) file_get_contents($root.'/src/Service/AttachmentStorage.php');
        $controller = (string) file_get_contents($root.'/src/Controller/AttachmentController.php');
        $migration = (string) file_get_contents($root.'/migrations/Version20260727234500.php');
        $services = (string) file_get_contents($root.'/config/services.yaml');
        $layout = (string) file_get_contents($root.'/templates/layout/app.html.twig');
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');

        self::assertStringContainsString("app.version: '0.9.2-M9.2-A-HF1'", $services);
        self::assertStringContainsString("app.attachment_storage_dir: '%kernel.project_dir%/var/storage/attachments'", $services);
        self::assertStringContainsString("#[ORM\Table(name: 'attachment')]", $entity);
        self::assertStringContainsString('private string $storageKey', $entity);
        self::assertStringContainsString('private string $sha256', $entity);
        self::assertStringContainsString('MAX_SIZE_BYTES = 10_485_760', $storage);
        self::assertStringContainsString("'pdf' => ['application/pdf']", $storage);
        self::assertStringContainsString('EICAR-STANDARD-ANTIVIRUS-TEST-FILE', $storage);
        self::assertStringContainsString("#[Route('/documenti'", $controller);
        self::assertStringContainsString('ResponseHeaderBag::DISPOSITION_ATTACHMENT', $controller);
        self::assertStringContainsString("createTable('attachment')", $migration);
        self::assertStringContainsString('nav-link-title">Documenti', $layout);
        self::assertStringContainsString('@("scripts/attachment-storage-contract.php")', $validation);
        self::assertStringContainsString('M9.2-A HOTFIX 1 VALIDATION PASSED', $validation);
    }


    public function testM7Hotfix1AttachmentTestsPreserveOriginalBasenameAndBrowserSessionCsrf(): void
    {
        $test = (string) file_get_contents(dirname(__DIR__, 2).'/tests/Controller/AttachmentManagementTest.php');

        self::assertStringContainsString("\$directory = \$root.'/'.bin2hex(random_bytes(8));", $test);
        self::assertStringContainsString("\$path = \$directory.'/'.\$name;", $test);
        self::assertStringContainsString("\$csrfToken = (string) \$uploadForm['attachment_upload[_token]']->getValue();", $test);
        self::assertStringNotContainsString('CsrfTokenManagerInterface', $test);
        self::assertStringContainsString("app.version: '0.9.2-M9.2-A-HF1'", (string) file_get_contents(dirname(__DIR__, 2).'/config/services.yaml'));
        self::assertStringContainsString('M9.2-A HOTFIX 1 VALIDATION PASSED', (string) file_get_contents(dirname(__DIR__, 2).'/scripts/validate.ps1'));
    }


    public function testM7DoesNotExposeStoredFilesUnderPublic(): void
    {
        $root = dirname(__DIR__, 2);
        $services = (string) file_get_contents($root.'/config/services.yaml');
        $storage = (string) file_get_contents($root.'/src/Service/AttachmentStorage.php');
        $gitignore = (string) file_get_contents($root.'/.gitignore');

        self::assertStringContainsString("%kernel.project_dir%/var/storage/attachments", $services);
        self::assertStringNotContainsString("%kernel.project_dir%/public", $services);
        self::assertStringNotContainsString('/public/', $storage);
        self::assertStringContainsString('/var/*', $gitignore);
    }

    public function testM8MonthlyReportAndEconomicsVisibilityContractsArePresent(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/src/Controller/MonthlyReportController.php');
        $repository = (string) file_get_contents($root.'/src/Repository/MonthlyReportRepository.php');
        $economics = (string) file_get_contents($root.'/src/Controller/EconomicsController.php');
        $voter = (string) file_get_contents($root.'/src/Security/Voter/ExpenseVoter.php');
        $template = (string) file_get_contents($root.'/templates/report/monthly.html.twig');
        $layout = (string) file_get_contents($root.'/templates/layout/app.html.twig');
        $validation = (string) file_get_contents($root.'/scripts/validate.ps1');

        self::assertStringContainsString("app.version: '0.9.2-M9.2-A-HF1'", (string) file_get_contents($root.'/config/services.yaml'));
        self::assertStringContainsString("#[Route('/report/mensile')]", $controller);
        self::assertStringContainsString("#[IsGranted('ROLE_PARTNER')]", $controller);
        self::assertStringContainsString("name: 'app_monthly_report_csv'", $controller);
        self::assertStringContainsString('findProjectMetrics', $repository);
        self::assertStringContainsString('findTimeEntries', $repository);
        self::assertStringContainsString('findActionCounts', $repository);
        self::assertStringContainsString('findForProjectAndRecorder', $economics);
        self::assertStringContainsString("public const MANAGE = 'EXPENSE_MANAGE'", $voter);
        self::assertStringContainsString('Andamento per commessa', $template);
        self::assertStringContainsString('Registrazioni ore del mese', $template);
        self::assertStringContainsString('Totali per azione', $template);
        self::assertStringContainsString('nav-link-title">Report mensile', $layout);
        self::assertStringContainsString('@("scripts/monthly-report-contract.php")', $validation);
        self::assertStringContainsString('M9.2-A HOTFIX 1 VALIDATION PASSED', $validation);
    }

}
