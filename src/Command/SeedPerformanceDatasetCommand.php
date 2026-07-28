<?php

declare(strict_types=1);

namespace App\Command;

use App\Performance\CapacityProfile;
use App\Service\PerformanceDatasetSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:performance:seed', description: 'Carica un dataset temporaneo deterministico per i benchmark 30/200/600.')]
final class SeedPerformanceDatasetCommand extends Command
{
    public function __construct(
        private readonly PerformanceDatasetSeeder $seeder,
        private readonly string $kernelEnvironment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('projects', null, InputOption::VALUE_REQUIRED, 'Numero di commesse: 30, 200 oppure 600.', '30')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Azzera il database benchmark prima del caricamento.')
            ->addOption('confirm', null, InputOption::VALUE_REQUIRED, 'Deve essere esattamente BENCHMARK.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!in_array($this->kernelEnvironment, ['dev', 'test'], true)) {
            $io->error('Il dataset prestazionale è consentito esclusivamente negli ambienti dev e test.');

            return Command::FAILURE;
        }
        if ('BENCHMARK' !== $input->getOption('confirm')) {
            $io->error('Caricamento annullato: usare --confirm=BENCHMARK su un database temporaneo.');

            return Command::INVALID;
        }

        $projectValue = (string) $input->getOption('projects');
        if (!ctype_digit($projectValue)) {
            $io->error('Il numero di commesse non è valido.');

            return Command::INVALID;
        }

        try {
            $profile = CapacityProfile::fromProjectCount((int) $projectValue);
            $summary = $this->seeder->seed($profile, (bool) $input->getOption('reset'));
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success([
            'Dataset prestazionale caricato in modo deterministico.',
            sprintf('%d utenti · %d clienti · %d commesse.', $summary->users, $summary->clients, $summary->projects),
            sprintf('%d attività · %d registrazioni ore.', $summary->activities, $summary->timeEntries),
            sprintf('%d spese · %d incassi · %d audit · %d allegati.', $summary->expenses, $summary->payments, $summary->audits, $summary->attachments),
            'Credenziali benchmark: benchmark.socio1 / Benchmark-accesso-2026!',
        ]);

        return Command::SUCCESS;
    }
}
