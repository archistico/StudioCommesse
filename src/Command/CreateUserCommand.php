<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Crea un utente iniziale o un nuovo account da console.',
)]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('username', InputArgument::OPTIONAL, 'Nome utente')
            ->addArgument('display-name', InputArgument::OPTIONAL, 'Nome visualizzato')
            ->addOption(
                'role',
                null,
                InputOption::VALUE_REQUIRED,
                'Ruolo: partner oppure collaborator',
                'partner',
            )
            ->addOption(
                'skip-if-active-partner-exists',
                null,
                InputOption::VALUE_NONE,
                'Termina senza creare utenti quando esiste già almeno un socio attivo.',
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $role = $this->resolveRole((string) $input->getOption('role'));
        if (
            true === $input->getOption('skip-if-active-partner-exists')
            && UserRole::Partner === $role
            && $this->userRepository->countActiveByRole(UserRole::Partner) > 0
        ) {
            return;
        }

        $io = new SymfonyStyle($input, $output);

        if ('' === trim((string) $input->getArgument('username'))) {
            $username = $io->ask('Nome utente per il login');
            $input->setArgument('username', is_string($username) ? $username : '');
        }

        if ('' === trim((string) $input->getArgument('display-name'))) {
            $displayName = $io->ask('Nome visualizzato (nome e cognome)');
            $input->setArgument('display-name', is_string($displayName) ? $displayName : '');
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $role = $this->resolveRole((string) $input->getOption('role'));

        if (null === $role) {
            $io->error('Ruolo non valido. Usare partner oppure collaborator.');

            return Command::INVALID;
        }

        if (
            true === $input->getOption('skip-if-active-partner-exists')
            && UserRole::Partner === $role
            && $this->userRepository->countActiveByRole(UserRole::Partner) > 0
        ) {
            $io->note('È già presente almeno un socio attivo: creazione iniziale non necessaria.');

            return Command::SUCCESS;
        }

        $username = mb_strtolower(trim((string) $input->getArgument('username')));
        $displayName = trim((string) $input->getArgument('display-name'));

        if ('' === $displayName || mb_strlen($displayName) > 120) {
            $io->error('Il nome visualizzato è obbligatorio e non può superare 120 caratteri.');

            return Command::INVALID;
        }

        if (1 !== preg_match('/^[a-z0-9._-]{3,120}$/', $username)) {
            $io->error('Il nome utente deve contenere da 3 a 120 caratteri tra lettere minuscole, numeri, punto, trattino e underscore.');

            return Command::INVALID;
        }

        if (null !== $this->userRepository->findOneBy(['username' => $username])) {
            $io->error('Esiste già un utente con questo nome utente.');

            return Command::FAILURE;
        }

        $password = $io->askHidden('Password (almeno 12 caratteri)');
        if (!is_string($password) || mb_strlen($password) < 12) {
            $io->error('La password deve contenere almeno 12 caratteri.');

            return Command::INVALID;
        }

        $user = (new User())
            ->setUsername($username)
            ->setDisplayName($displayName)
            ->setRole($role)
            ->setActive(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->userRepository->save($user, true);
        $io->success(sprintf('Utente "%s" creato come %s.', $username, $role->label()));

        return Command::SUCCESS;
    }

    private function resolveRole(string $roleInput): ?UserRole
    {
        return match (mb_strtolower(trim($roleInput))) {
            'partner', 'socio' => UserRole::Partner,
            'collaborator', 'collaboratore' => UserRole::Collaborator,
            default => null,
        };
    }
}
