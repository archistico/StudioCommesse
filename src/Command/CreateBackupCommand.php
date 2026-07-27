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

#[AsCommand(name: 'app:backup:create', description: 'Crea un backup coordinato di SQLite e allegati.')] 
final class CreateBackupCommand extends Command
{
    public function __construct(private readonly BackupManager $backupManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('directory', InputArgument::REQUIRED, 'Directory nuova in cui creare il backup non compresso.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = $input->getArgument('directory');
        if (!is_string($directory) || '' === trim($directory)) {
            $io->error('Specificare una directory di destinazione valida.');

            return Command::INVALID;
        }

        try {
            $summary = $this->backupManager->create($directory);
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        $io->success([
            'Backup coordinato creato e verificato.',
            'Directory: '.$summary->directory,
            'Versione applicativa: '.$summary->appVersion,
            sprintf('Database: %d byte · SHA-256 %s', $summary->databaseBytes, $summary->databaseSha256),
            sprintf('Allegati: %d file · %d byte', $summary->attachmentCount, $summary->attachmentBytes),
        ]);

        return Command::SUCCESS;
    }
}
