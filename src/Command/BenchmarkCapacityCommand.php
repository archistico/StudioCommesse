<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\User;
use App\Performance\CapacityProfile;
use App\Query\AuditSearchCriteria;
use App\Query\ControlSearchCriteria;
use App\Query\ProjectSearchCriteria;
use App\Query\TimeEntrySearchCriteria;
use App\Repository\ActivityRepository;
use App\Repository\AuditLogRepository;
use App\Repository\DashboardRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\UserRepository;
use App\Service\BackupManager;
use App\Service\MonthlyReportService;
use App\Service\ProjectControlService;
use App\Service\ProjectFinancialService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:performance:benchmark', description: 'Misura i principali percorsi di lettura e backup sul dataset di capacità.')]
final class BenchmarkCapacityCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DashboardRepository $dashboard,
        private readonly ProjectRepository $projects,
        private readonly ActivityRepository $activities,
        private readonly TimeEntryRepository $timeEntries,
        private readonly UserRepository $users,
        private readonly ProjectControlService $control,
        private readonly ProjectFinancialService $financial,
        private readonly MonthlyReportService $monthlyReport,
        private readonly AuditLogRepository $audit,
        private readonly BackupManager $backupManager,
        private readonly string $kernelEnvironment,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('projects', null, InputOption::VALUE_REQUIRED, 'Profilo atteso: 30, 200 oppure 600.', '30')
            ->addOption('iterations', null, InputOption::VALUE_REQUIRED, 'Ripetizioni per metrica.', '3')
            ->addOption('json', null, InputOption::VALUE_REQUIRED, 'Percorso facoltativo del rapporto JSON.')
            ->addOption('enforce', null, InputOption::VALUE_NONE, 'Fallisce quando una mediana supera il budget del profilo.')
            ->addOption('skip-backup', null, InputOption::VALUE_NONE, 'Esclude create/verify/restore del backup isolato.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!in_array($this->kernelEnvironment, ['dev', 'test'], true)) {
            $io->error('Il benchmark è consentito esclusivamente negli ambienti dev e test.');

            return Command::FAILURE;
        }

        try {
            $profile = CapacityProfile::fromProjectCount((int) $input->getOption('projects'));
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }
        $iterations = max(1, min(10, (int) $input->getOption('iterations')));
        $actualProjects = (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM project');
        if ($actualProjects !== $profile->projectCount()) {
            $io->error(sprintf('Dataset non coerente: attese %d commesse, trovate %d.', $profile->projectCount(), $actualProjects));

            return Command::FAILURE;
        }

        $now = new DateTimeImmutable('2026-07-28 12:00:00');
        $month = new DateTimeImmutable('2026-07-01 00:00:00');
        $from = $now->modify('-12 months');
        $before = $now->modify('+1 day');
        /** @var array<string, array{median_ms: float, p95_ms: float, average_ms: float, samples_ms: list<float>}> $metrics */
        $metrics = [];

        /** @var array<string, callable(): mixed> $operations */
        $operations = [
            'dashboard' => fn (): mixed => $this->dashboard->summarize($month, $month->modify('+1 month'), $now),
            'commesse' => fn (): mixed => $this->projects->findFiltered(new ProjectSearchCriteria()),
            'attivita' => function (): mixed {
                $user = $this->users->find(3);
                if (!$user instanceof User) {
                    throw new \RuntimeException('Utente benchmark non disponibile.');
                }
                $activities = $this->activities->findForAssignee($user);

                return [$activities, $this->timeEntries->sumMinutesByActivityIds($this->activityIds($activities))];
            },
            'ore' => fn (): mixed => [
                $this->timeEntries->findPage(new TimeEntrySearchCriteria(startedFrom: $from, startedBefore: $before, page: 1, perPage: 50)),
                $this->timeEntries->summarize(new TimeEntrySearchCriteria(startedFrom: $from, startedBefore: $before)),
            ],
            'controllo' => fn (): mixed => $this->control->build(new ControlSearchCriteria(periodFrom: $from, periodBefore: $before), $now),
            'economia' => function (): mixed {
                $projects = $this->projects->findForEconomics();

                return $this->financial->summarizeMany($projects);
            },
            'report_mensile' => fn (): mixed => $this->monthlyReport->build($month),
            'audit' => fn (): mixed => [
                $this->audit->findPage(new AuditSearchCriteria(page: 1, perPage: 50)),
                $this->audit->summarize(new AuditSearchCriteria()),
            ],
            'dettaglio_commessa' => function (): mixed {
                $project = $this->projects->find(1);
                if (!$project instanceof Project) {
                    throw new \RuntimeException('Commessa benchmark non disponibile.');
                }
                $activities = $this->activities->findForProject($project);

                return [$activities, $this->timeEntries->summarizeMinutesByActivityAndUserIds($this->activityIds($activities))];
            },
        ];

        try {
            foreach ($operations as $name => $operation) {
                $metrics[$name] = $this->measure($operation, $iterations);
            }
            if (!$input->getOption('skip-backup')) {
                $metrics += $this->measureBackup($profile);
            }
        } catch (\Throwable $exception) {
            $io->error('Benchmark interrotto: '.$exception->getMessage());

            return Command::FAILURE;
        }

        $budgets = $this->budgets($profile);
        $violations = [];
        $rows = [];
        foreach ($metrics as $name => $metric) {
            $budget = $budgets[$name] ?? $budgets['default'];
            $ok = $metric['median_ms'] <= $budget;
            if (!$ok) {
                $violations[] = sprintf('%s: %.1f ms > %.1f ms', $name, $metric['median_ms'], $budget);
            }
            $rows[] = [$name, number_format($metric['median_ms'], 1, ',', '.'), number_format($metric['p95_ms'], 1, ',', '.'), number_format($budget, 0, ',', '.'), $ok ? 'OK' : 'FUORI BUDGET'];
        }

        $io->title('Benchmark capacità · '.$profile->label());
        $io->table(['Percorso', 'Mediana ms', 'P95 ms', 'Budget ms', 'Esito'], $rows);
        $io->writeln(sprintf('Picco memoria PHP: %.1f MB', memory_get_peak_usage(true) / 1_048_576));

        $report = [
            'generated_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            'profile' => $profile->label(),
            'project_count' => $profile->projectCount(),
            'iterations' => $iterations,
            'php_version' => PHP_VERSION,
            'peak_memory_bytes' => memory_get_peak_usage(true),
            'metrics' => $metrics,
            'budgets_ms' => $budgets,
            'violations' => $violations,
        ];
        $jsonPath = trim((string) $input->getOption('json'));
        if ('' !== $jsonPath) {
            $this->writeJson($jsonPath, $report);
            $io->writeln('Rapporto JSON: '.$jsonPath);
        }

        if ((bool) $input->getOption('enforce') && [] !== $violations) {
            $io->error(array_merge(['Budget prestazionali non rispettati.'], $violations));

            return Command::FAILURE;
        }

        $io->success('Benchmark completato senza violazioni bloccanti.');

        return Command::SUCCESS;
    }


    /**
     * @param iterable<Activity> $activities
     *
     * @return list<int>
     */
    private function activityIds(iterable $activities): array
    {
        $ids = [];
        foreach ($activities as $activity) {
            $id = $activity->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param callable(): mixed $operation
     *
     * @return array{median_ms: float, p95_ms: float, average_ms: float, samples_ms: list<float>}
     */
    private function measure(callable $operation, int $iterations, bool $warmup = true): array
    {
        if ($warmup) {
            $this->entityManager->clear();
            $operation();
        }
        $samples = [];
        for ($index = 0; $index < $iterations; ++$index) {
            $this->entityManager->clear();
            $start = hrtime(true);
            $operation();
            $samples[] = (hrtime(true) - $start) / 1_000_000;
        }
        sort($samples, SORT_NUMERIC);
        $count = count($samples);
        $middle = intdiv($count, 2);
        $median = 1 === $count % 2 ? $samples[$middle] : ($samples[$middle - 1] + $samples[$middle]) / 2;
        $p95Index = max(0, (int) ceil($count * 0.95) - 1);

        return [
            'median_ms' => $median,
            'p95_ms' => $samples[$p95Index],
            'average_ms' => array_sum($samples) / $count,
            'samples_ms' => $samples,
        ];
    }

    /** @return array<string, array{median_ms: float, p95_ms: float, average_ms: float, samples_ms: list<float>}> */
    private function measureBackup(CapacityProfile $profile): array
    {
        $root = $this->projectDirectory.DIRECTORY_SEPARATOR.'var'.DIRECTORY_SEPARATOR.'performance'.DIRECTORY_SEPARATOR.'backup-'.$profile->projectCount().'-'.bin2hex(random_bytes(4));
        $source = $root.DIRECTORY_SEPARATOR.'source';
        $safety = $root.DIRECTORY_SEPARATOR.'safety';
        if (!mkdir($root, 0700, true) && !is_dir($root)) {
            throw new \RuntimeException('Impossibile creare la directory temporanea del benchmark backup.');
        }
        try {
            $create = $this->measure(fn (): mixed => $this->backupManager->create($source), 1, false);
            $verify = $this->measure(fn (): mixed => $this->backupManager->verify($source), 1, false);
            $restore = $this->measure(fn (): mixed => $this->backupManager->restore($source, $safety), 1, false);

            return ['backup_create' => $create, 'backup_verify' => $verify, 'backup_restore' => $restore];
        } finally {
            $this->removeDirectory($root);
        }
    }

    /** @return array<string, float> */
    private function budgets(CapacityProfile $profile): array
    {
        return match ($profile) {
            CapacityProfile::Small => ['default' => 300.0, 'report_mensile' => 600.0, 'backup_create' => 8_000.0, 'backup_verify' => 4_000.0, 'backup_restore' => 12_000.0],
            CapacityProfile::Medium => ['default' => 900.0, 'report_mensile' => 1_800.0, 'backup_create' => 20_000.0, 'backup_verify' => 8_000.0, 'backup_restore' => 30_000.0],
            CapacityProfile::Large => ['default' => 2_500.0, 'report_mensile' => 5_000.0, 'backup_create' => 45_000.0, 'backup_verify' => 15_000.0, 'backup_restore' => 70_000.0],
        };
    }

    /** @param array<string, mixed> $report */
    private function writeJson(string $path, array $report): void
    {
        if (!str_starts_with($path, DIRECTORY_SEPARATOR) && 1 !== preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = $this->projectDirectory.DIRECTORY_SEPARATOR.$path;
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossibile creare la directory del rapporto benchmark.');
        }
        $json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (false === file_put_contents($path, $json."\n")) {
            throw new \RuntimeException('Impossibile scrivere il rapporto benchmark.');
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
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($directory);
    }
}
