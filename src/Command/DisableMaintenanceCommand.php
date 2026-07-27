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

#[AsCommand(name: 'app:maintenance:disable', description: 'Disattiva una modalità manutenzione rimasta attiva dopo un ripristino fallito.')]
final class DisableMaintenanceCommand extends Command
{
    public function __construct(private readonly MaintenanceMode $maintenanceMode)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('confirm', null, InputOption::VALUE_REQUIRED, 'Deve essere esattamente CLEAR.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ('CLEAR' !== $input->getOption('confirm')) {
            $io->error('Operazione annullata: usare --confirm=CLEAR dopo aver verificato database e allegati.');

            return Command::INVALID;
        }
        if (!$this->maintenanceMode->isEnabled()) {
            $io->success('La modalità manutenzione non è attiva.');

            return Command::SUCCESS;
        }

        try {
            $this->maintenanceMode->disable();
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success('Modalità manutenzione disattivata.');

        return Command::SUCCESS;
    }
}
