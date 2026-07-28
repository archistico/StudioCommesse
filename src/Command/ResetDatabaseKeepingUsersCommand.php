<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\DatabaseDataResetter;
use App\Service\FileLock;
use App\Service\MaintenanceMode;
use App\Service\RequestRuntimeLock;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:database:reset-keep-users',
    description: 'Cancella tutti i dati applicativi conservando utenti e cronologia delle migrazioni.',
)]
final class ResetDatabaseKeepingUsersCommand extends Command
{
    public function __construct(
        private readonly DatabaseDataResetter $resetter,
        private readonly MaintenanceMode $maintenanceMode,
        private readonly RequestRuntimeLock $requestLock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'confirm',
            null,
            InputOption::VALUE_REQUIRED,
            'Deve essere esattamente KEEP-USERS.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ('KEEP-USERS' !== $input->getOption('confirm')) {
            $io->error('Operazione annullata: usare --confirm=KEEP-USERS.');

            return Command::INVALID;
        }

        $ownsMaintenanceMode = !$this->maintenanceMode->isEnabled();
        /** @var FileLock|null $lock */
        $lock = null;

        try {
            if ($ownsMaintenanceMode) {
                $this->maintenanceMode->enable('Azzeramento dei dati applicativi in corso.');
            }

            $lock = $this->requestLock->acquireExclusive();
            $summary = $this->resetter->resetKeepingUsers();

            if ($ownsMaintenanceMode) {
                $this->maintenanceMode->disable();
                $ownsMaintenanceMode = false;
            }
        } catch (\Throwable $exception) {
            if ($ownsMaintenanceMode) {
                try {
                    $this->maintenanceMode->disable();
                } catch (\Throwable) {
                    $io->warning('Impossibile disattivare automaticamente la modalità manutenzione.');
                }
            }
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } finally {
            $lock?->release();
        }

        $deletedRows = array_sum($summary['deleted']);
        $io->success([
            sprintf('%d righe applicative eliminate.', $deletedRows),
            sprintf('%d utenti conservati.', $summary['users']),
            'Cronologia delle migrazioni conservata.',
            'I file degli allegati non sono stati eliminati.',
        ]);

        return Command::SUCCESS;
    }
}
