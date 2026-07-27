<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Activity;
use App\Entity\Client;
use App\Entity\Expense;
use App\Entity\Payment;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\ActivityPriority;
use App\Enum\ActivityStatus;
use App\Enum\ProjectPriority;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Repository\ActivityRepository;
use App\Repository\ClientRepository;
use App\Repository\ExpenseRepository;
use App\Repository\PaymentRepository;
use App\Repository\ProjectRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\UserRepository;
use App\Service\HourlyRateResolver;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:fixtures:load', description: 'Carica dati dimostrativi idempotenti per Studio Commesse.')]
final class LoadDemoFixturesCommand extends Command
{
    private const DEMO_PASSWORD = 'Demo-accesso-2026!';
    private const DEMO_PROJECT_COUNT = 30;
    private const DEMO_ACTIVITY_COUNT = 200;
    private const DEMO_TIME_ENTRY_COUNT = 600;
    private const DEMO_ENTRY_MARKER = 'Fixture standard M5 HF4';

    /** @var list<string> */
    private const PHASES = [
        'Rilievo',
        'Analisi',
        'Progettazione',
        'Verifica',
        'Consegna',
        'Revisione cliente',
        'Chiusura documentale',
    ];

    /** @var list<int> */
    private const CONTRIBUTOR_OFFSETS = [0, 1, 2, 0, 3, 1, 4, 2];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly ClientRepository $clients,
        private readonly ProjectRepository $projects,
        private readonly ActivityRepository $activities,
        private readonly TimeEntryRepository $timeEntries,
        private readonly ExpenseRepository $expenses,
        private readonly PaymentRepository $payments,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly HourlyRateResolver $rateResolver,
        private readonly string $kernelEnvironment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Consente il caricamento anche fuori da dev/test.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!in_array($this->kernelEnvironment, ['dev', 'test'], true) && !$input->getOption('force')) {
            $io->error('Le fixtures sono consentite solo in dev/test. Usare --force consapevolmente.');

            return Command::FAILURE;
        }

        $demoUsers = $this->loadUsers();
        $this->entityManager->flush();
        $demoClients = $this->loadClients();
        $this->entityManager->flush();
        $demoProjects = $this->loadProjects($demoClients, $demoUsers);
        $this->entityManager->flush();
        [$activityCount, $timeEntryCount] = $this->loadActivitiesAndTimeEntries($demoProjects, $demoUsers);
        $this->entityManager->flush();
        [$expenseCount, $paymentCount] = $this->loadEconomics($demoProjects, $demoUsers[0]);
        $this->entityManager->flush();

        $io->success([
            'Fixtures caricate o riconciliate correttamente.',
            sprintf('%d utenti, %d clienti, %d commesse.', count($demoUsers), count($demoClients), count($demoProjects)),
            sprintf('%d attività, %d registrazioni ore, %d spese, %d incassi.', $activityCount, $timeEntryCount, $expenseCount, $paymentCount),
            'Le ore demo coinvolgono assegnatari, altri collaboratori e soci.',
            'Utente socio principale: demo.socio',
            'Password comune: '.self::DEMO_PASSWORD,
        ]);

        return Command::SUCCESS;
    }

    /** @return list<User> */
    private function loadUsers(): array
    {
        $definitions = [
            ['demo.socio', 'Socio Demo', UserRole::Partner, 7500],
            ['demo.socia', 'Elena Ferri', UserRole::Partner, 7800],
            ['anna.rossi', 'Anna Rossi', UserRole::Collaborator, 4800],
            ['luca.bianchi', 'Luca Bianchi', UserRole::Collaborator, 5200],
            ['marco.verdi', 'Marco Verdi', UserRole::Collaborator, 5000],
            ['sara.neri', 'Sara Neri', UserRole::Collaborator, 4600],
            ['giulia.romano', 'Giulia Romano', UserRole::Collaborator, 5500],
            ['davide.conti', 'Davide Conti', UserRole::Collaborator, 4900],
        ];

        $result = [];
        foreach ($definitions as [$username, $displayName, $role, $rate]) {
            $user = $this->users->findOneBy(['username' => $username]);
            if (!$user instanceof User) {
                $user = (new User())->setUsername($username);
                $user->setPassword($this->passwordHasher->hashPassword($user, self::DEMO_PASSWORD));
                $this->entityManager->persist($user);
            }

            $user
                ->setDisplayName($displayName)
                ->setRole($role)
                ->setActive(true)
                ->setDefaultHourlyRateCents($rate);
            $result[] = $user;
        }

        return $result;
    }

    /** @return list<Client> */
    private function loadClients(): array
    {
        $definitions = [
            ['Alfa Impianti S.r.l.', 'Paolo Verdi', 'paolo.verdi@example.test'],
            ['Comune di Rivabella', 'Ufficio Tecnico', 'tecnico.rivabella@example.test'],
            ['Beta Costruzioni S.p.A.', 'Marta Villa', 'marta.villa@example.test'],
            ['Condominio Le Magnolie', 'Amministrazione', 'magnolie@example.test'],
            ['Gamma Energia S.r.l.', 'Andrea Sala', 'andrea.sala@example.test'],
            ['Fondazione Aurora', 'Lucia Fontana', 'lucia.fontana@example.test'],
            ['Delta Logistica S.r.l.', 'Roberto Greco', 'roberto.greco@example.test'],
            ['Provincia di Valleverde', 'Settore Lavori Pubblici', 'llpp@example.test'],
            ['Officine Nord S.r.l.', 'Chiara Riva', 'chiara.riva@example.test'],
            ['Residenza Il Glicine', 'Studio Amministrazioni', 'glicine@example.test'],
        ];

        $result = [];
        foreach ($definitions as [$name, $contact, $email]) {
            $client = $this->clients->findOneBy(['name' => $name]);
            if (!$client instanceof Client) {
                $client = (new Client())->setName($name);
                $this->entityManager->persist($client);
            }

            $client
                ->setContactPerson($contact)
                ->setEmail($email);
            $result[] = $client;
        }

        return $result;
    }

    /**
     * @param list<Client> $clients
     * @param list<User> $users
     * @return list<Project>
     */
    private function loadProjects(array $clients, array $users): array
    {
        $names = [
            'Adeguamento impianto elettrico',
            'Riqualificazione edificio comunale',
            'Verifica vulnerabilità sismica',
            'Progetto antincendio stabilimento',
            'Efficientamento energetico uffici',
            'Ristrutturazione copertura',
            'Nuovo impianto fotovoltaico',
            'Direzione lavori capannone',
            'Pratica edilizia ampliamento',
            'Collaudo impianti meccanici',
            'Rilievo e restituzione fabbricato',
            'Piano manutenzione immobili',
            'Studio fattibilità comunità energetica',
            'Adeguamento prevenzione incendi',
            'Progettazione rete dati',
            'Diagnosi energetica condominio',
            'Consolidamento strutturale',
            'Coordinamento sicurezza cantiere',
            'Variante urbanistica produttiva',
            'Rifacimento centrale termica',
            'Progetto illuminazione esterna',
            'Restauro facciate storiche',
            'Verifica impianto di terra',
            'Progetto esecutivo palestra',
            'Adeguamento accessibilità edificio',
            'Rifacimento rete fognaria interna',
            'Studio acustico nuovo auditorium',
            'Riqualificazione piazza civica',
            'Adeguamento impianto di climatizzazione',
            'Progetto pensilina area logistica',
        ];
        $statuses = [ProjectStatus::InProgress, ProjectStatus::Waiting, ProjectStatus::NotStarted, ProjectStatus::Completed];
        $priorities = [ProjectPriority::Normal, ProjectPriority::High, ProjectPriority::Urgent, ProjectPriority::Low];
        $result = [];

        foreach ($names as $index => $name) {
            $code = sprintf('2026-%03d', 901 + $index);
            $project = $this->projects->findOneBy(['code' => $code]);
            if (!$project instanceof Project) {
                $project = new Project();
                $project->assignCode($code);
                $this->entityManager->persist($project);
            }

            $status = $statuses[$index % count($statuses)];
            $project
                ->setName($name)
                ->setClient($clients[$index % count($clients)])
                ->setResponsible($users[$index % 2])
                ->setStatus($status)
                ->setPriority($priorities[$index % count($priorities)])
                ->setDescription('Commessa dimostrativa generata dalle fixtures M5 Hotfix 4.')
                ->setStartDate(new DateTimeImmutable(sprintf('2026-%02d-%02d', 1 + intdiv($index, 5), 2 + ($index % 20))))
                ->setDueDate(new DateTimeImmutable(sprintf('2026-%02d-%02d', 7 + intdiv($index, 10), 5 + ($index % 20))))
                ->setEstimatedAmountCents(650000 + ($index * 87500))
                ->setDefaultHourlyRateCents(5800 + (($index % 5) * 350))
                ->setWaitingReason(ProjectStatus::Waiting === $status ? 'In attesa di documentazione o conferma del cliente.' : null);
            $result[] = $project;
        }

        if (self::DEMO_PROJECT_COUNT !== count($result)) {
            throw new \LogicException('Il profilo fixtures deve contenere esattamente 30 commesse.');
        }

        return $result;
    }

    /**
     * @param list<Project> $projects
     * @param list<User> $users
     * @return array{int, int}
     */
    private function loadActivitiesAndTimeEntries(array $projects, array $users): array
    {
        $activityCount = 0;
        $timeEntryCount = 0;
        $timeSlot = 0;
        $base = new DateTimeImmutable('2026-01-05 08:00');

        foreach ($projects as $projectIndex => $project) {
            $activitiesForProject = $projectIndex < 20 ? 7 : 6;
            for ($phaseIndex = 0; $phaseIndex < $activitiesForProject; ++$phaseIndex) {
                $phase = self::PHASES[$phaseIndex];
                $title = sprintf('Fase %d — %s', $phaseIndex + 1, $phase);
                $activity = $this->activities->findOneBy(['project' => $project, 'title' => $title]);
                if (!$activity instanceof Activity) {
                    $activity = (new Activity())
                        ->setProject($project)
                        ->setTitle($title);
                    $this->entityManager->persist($activity);
                }

                $assigneeIndex = ($projectIndex * 3 + $phaseIndex) % count($users);
                $assignee = $users[$assigneeIndex];
                $status = match (($projectIndex + $phaseIndex) % 5) {
                    0 => ActivityStatus::Completed,
                    1, 2 => ActivityStatus::InProgress,
                    3 => ActivityStatus::Waiting,
                    default => ActivityStatus::NotStarted,
                };
                $progress = match ($status) {
                    ActivityStatus::Completed => 100,
                    ActivityStatus::InProgress => 30 + (($projectIndex * 7 + $phaseIndex * 11) % 61),
                    ActivityStatus::Waiting => 20,
                    default => 0,
                };
                $initialMinutes = 240 + ($phaseIndex * 105) + (($projectIndex % 4) * 60);
                $remainingMinutes = ActivityStatus::Completed === $status
                    ? 0
                    : (int) round($initialMinutes * (100 - $progress) / 100);

                $activity
                    ->setProject($project)
                    ->setAssignee($assignee)
                    ->setCreatedBy($project->getResponsible())
                    ->setDescription(sprintf('%s della commessa %s.', $phase, $project->getCode()))
                    ->setStatus($status)
                    ->setPriority(ActivityPriority::cases()[($projectIndex + $phaseIndex) % count(ActivityPriority::cases())])
                    ->setProgressPercent($progress)
                    ->setInitialEstimatedMinutes($initialMinutes)
                    ->setRemainingEstimatedMinutes($remainingMinutes)
                    ->setHourlyRateOverrideCents(2 === $phaseIndex && 0 === $projectIndex % 3 ? 8200 : null)
                    ->setStartAt($base->modify(sprintf('+%d days', $projectIndex * 2 + $phaseIndex)))
                    ->setDueAt($base->modify(sprintf('+%d days', 20 + $projectIndex * 3 + $phaseIndex * 4)));

                $this->removeManagedFixtureEntries($activity);

                $entryTarget = $this->entryTargetForActivity($activityCount);
                for ($entryIndex = 0; $entryIndex < $entryTarget; ++$entryIndex) {
                    $startedAt = $base->modify(sprintf('+%d minutes', $timeSlot * 240));
                    $durationMinutes = 45 + ((($activityCount + $entryIndex) % 6) * 30);
                    $endedAt = $startedAt->modify(sprintf('+%d minutes', $durationMinutes));
                    ++$timeSlot;

                    $workerIndex = ($assigneeIndex + self::CONTRIBUTOR_OFFSETS[$entryIndex]) % count($users);
                    $worker = $users[$workerIndex];
                    $entry = (new TimeEntry())
                        ->setActivity($activity)
                        ->setUser($worker)
                        ->setStartedAt($startedAt)
                        ->setEndedAt($endedAt)
                        ->setDescription(sprintf('%s · %s · registrazione %d.', self::DEMO_ENTRY_MARKER, $phase, $entryIndex + 1))
                        ->setBillable(0 !== (($activityCount + $entryIndex) % 5));
                    $entry->applyRateSnapshot($this->rateResolver->resolve($activity, $worker));
                    $this->entityManager->persist($entry);
                    ++$timeEntryCount;
                }

                ++$activityCount;
            }
        }

        if (self::DEMO_ACTIVITY_COUNT !== $activityCount || self::DEMO_TIME_ENTRY_COUNT !== $timeEntryCount) {
            throw new \LogicException(sprintf(
                'Profilo fixtures non valido: attese %d attività e %d registrazioni, ottenute %d e %d.',
                self::DEMO_ACTIVITY_COUNT,
                self::DEMO_TIME_ENTRY_COUNT,
                $activityCount,
                $timeEntryCount,
            ));
        }

        return [$activityCount, $timeEntryCount];
    }

    private function entryTargetForActivity(int $activityIndex): int
    {
        $distributedIndex = ($activityIndex * 73) % self::DEMO_ACTIVITY_COUNT;

        return match (true) {
            $distributedIndex < 20 => 0,
            $distributedIndex < 60 => 1,
            $distributedIndex < 120 => 2,
            $distributedIndex < 170 => 4,
            default => 8,
        };
    }

    private function removeManagedFixtureEntries(Activity $activity): void
    {
        if (null === $activity->getId()) {
            return;
        }

        foreach ($this->timeEntries->findForActivity($activity) as $entry) {
            $description = $entry->getDescription() ?? '';
            $isCurrentFixture = str_starts_with($description, self::DEMO_ENTRY_MARKER.' · ');
            $isLegacyFixture = 1 === preg_match(
                '/^(Rilievo|Analisi|Progettazione|Verifica|Consegna) — sessione \d+\.$/u',
                $description,
            );

            if ($isCurrentFixture || $isLegacyFixture) {
                $this->entityManager->remove($entry);
            }
        }
    }

    /**
     * @param list<Project> $projects
     * @return array{int, int}
     */
    private function loadEconomics(array $projects, User $recordedBy): array
    {
        $expenseCount = 0;
        $paymentCount = 0;

        foreach ($projects as $projectIndex => $project) {
            $projectActivities = $this->activities->findForProject($project);
            for ($index = 0; $index < 8; ++$index) {
                $description = sprintf('Spesa demo %s-%02d', $project->getCode(), $index + 1);
                $expense = $this->expenses->findOneBy(['project' => $project, 'description' => $description]);
                if (!$expense instanceof Expense) {
                    $expense = (new Expense())
                        ->setProject($project)
                        ->setDescription($description);
                    $this->entityManager->persist($expense);
                }

                $expense
                    ->setActivity($projectActivities[$index % count($projectActivities)] ?? null)
                    ->setRecordedBy($recordedBy)
                    ->setSpentOn(new DateTimeImmutable(sprintf('2026-%02d-%02d', 1 + (($projectIndex + $index) % 6), 2 + (($projectIndex * 3 + $index) % 25))))
                    ->setCategory(Expense::CATEGORIES[$index % count(Expense::CATEGORIES)])
                    ->setAmountCents(1800 + (($projectIndex * 850 + $index * 1275) % 22000))
                    ->setReimbursable(0 === $index % 3);
                ++$expenseCount;
            }

            for ($index = 0; $index < 4; ++$index) {
                $reference = sprintf('%s-SAL-%02d', $project->getCode(), $index + 1);
                $payment = $this->payments->findOneBy(['project' => $project, 'reference' => $reference]);
                if (!$payment instanceof Payment) {
                    $payment = (new Payment())
                        ->setProject($project)
                        ->setReference($reference);
                    $this->entityManager->persist($payment);
                }

                $payment
                    ->setRecordedBy($recordedBy)
                    ->setPaidOn(new DateTimeImmutable(sprintf('2026-%02d-%02d', 2 + (($projectIndex + $index) % 5), 3 + (($projectIndex * 2 + $index * 5) % 24))))
                    ->setAmountCents((int) round($project->getEstimatedAmountCents() * (0.12 + $index * 0.06)))
                    ->setDescription(sprintf('Acconto %d per %s', $index + 1, $project->getName()))
                    ->setMethod(Payment::METHODS[$index % count(Payment::METHODS)])
                    ->setNotes(3 === $index ? 'Saldo dimostrativo da verificare con il cliente.' : null);
                ++$paymentCount;
            }
        }

        return [$expenseCount, $paymentCount];
    }
}
