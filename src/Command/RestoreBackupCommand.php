<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\BackupManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:backup:restore', description: 'Ripristina in modo coordinato SQLite e allegati da un backup verificato.')] 
final class RestoreBackupCommand extends Command
{
    public function __construct(private readonly BackupManager $backupManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::REQUIRED, 'Directory estratta del backup da ripristinare.')
            ->addOption('safety-backup-dir', null, InputOption::VALUE_REQUIRED, 'Directory nuova per il backup automatico precedente al ripristino.')
            ->addOption('confirm', null, InputOption::VALUE_REQUIRED, 'Deve essere esattamente RESTORE.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = $input->getArgument('directory');
        $safetyDirectory = $input->getOption('safety-backup-dir');
        $confirmation = $input->getOption('confirm');

        if (!is_string($directory) || '' === trim($directory)
            || !is_string($safetyDirectory) || '' === trim($safetyDirectory)
        ) {
            $io->error('Specificare il backup da ripristinare e la directory del backup di sicurezza.');

            return Command::INVALID;
        }
        if ('RESTORE' !== $confirmation) {
            $io->error('Ripristino annullato: usare --confirm=RESTORE in modo esplicito.');

            return Command::INVALID;
        }

        $io->warning([
            'Il ripristino sostituirà il database e lo spazio documentale correnti.',
            'Durante l’operazione l’applicazione risponderà in modalità manutenzione.',
        ]);

        try {
            $result = $this->backupManager->restore($directory, $safetyDirectory);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success([
            'Ripristino coordinato completato e verificato.',
            'Backup ripristinato: '.$result->restoredBackup->createdAt,
            'Backup di sicurezza precedente al ripristino: '.$result->safetyBackup->directory,
        ]);

        return Command::SUCCESS;
    }
}
