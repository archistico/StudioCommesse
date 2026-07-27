<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\BackupManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:backup:verify', description: 'Verifica manifest, hash, database SQLite e allegati di un backup.')] 
final class VerifyBackupCommand extends Command
{
    public function __construct(private readonly BackupManager $backupManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('directory', InputArgument::REQUIRED, 'Directory estratta del backup da verificare.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = $input->getArgument('directory');
        if (!is_string($directory) || '' === trim($directory)) {
            $io->error('Specificare la directory del backup.');

            return Command::INVALID;
        }

        try {
            $summary = $this->backupManager->verify($directory);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success([
            'Backup integro e ripristinabile.',
            'Creato il: '.$summary->createdAt,
            'Versione applicativa: '.$summary->appVersion,
            sprintf('Database: %d byte', $summary->databaseBytes),
            sprintf('Allegati: %d file · %d byte', $summary->attachmentCount, $summary->attachmentBytes),
        ]);

        return Command::SUCCESS;
    }
}
