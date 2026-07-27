<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\MaintenanceMode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:maintenance:enable', description: 'Attiva esplicitamente la modalità manutenzione per operazioni coordinate.')]
final class EnableMaintenanceCommand extends Command
{
    public function __construct(private readonly MaintenanceMode $maintenanceMode)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('confirm', null, InputOption::VALUE_REQUIRED, 'Deve essere esattamente MAINTENANCE.')
            ->addOption('message', null, InputOption::VALUE_REQUIRED, 'Messaggio mostrato agli utenti.', 'Manutenzione programmata in corso.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ('MAINTENANCE' !== $input->getOption('confirm')) {
            $io->error('Operazione annullata: usare --confirm=MAINTENANCE.');

            return Command::INVALID;
        }
        if ($this->maintenanceMode->isEnabled()) {
            $io->error('La modalità manutenzione è già attiva.');

            return Command::FAILURE;
        }

        $message = $input->getOption('message');
        if (!is_string($message) || '' === trim($message)) {
            $message = 'Manutenzione programmata in corso.';
        }

        try {
            $this->maintenanceMode->enable($message);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('Modalità manutenzione attivata.');

        return Command::SUCCESS;
    }
}
