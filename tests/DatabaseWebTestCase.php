<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Activity;
use App\Entity\Client;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\ProjectPriority;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class DatabaseWebTestCase extends WebTestCase
{
    private int $projectNumber = 1;

    protected KernelBrowser $client;
    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient(['debug' => false]);
        $this->client->disableReboot();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function createUser(
        string $username,
        UserRole $role = UserRole::Collaborator,
        bool $active = true,
        string $password = 'Password-sicura-123!',
    ): User {
        $user = (new User())
            ->setDisplayName(ucfirst($username))
            ->setUsername($username)
            ->setRole($role)
            ->setActive($active);

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }


    protected function createCustomer(string $name = 'Cliente Test'): Client
    {
        $client = (new Client())->setName($name);
        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return $client;
    }

    protected function createProject(
        Client $client,
        User $responsible,
        string $name = 'Commessa Test',
        ProjectStatus $status = ProjectStatus::NotStarted,
        ProjectPriority $priority = ProjectPriority::Normal,
    ): Project {
        $project = (new Project())
            ->setName($name)
            ->setClient($client)
            ->setResponsible($responsible)
            ->setStatus($status)
            ->setPriority($priority);
        $project->assignCode(sprintf('2099-%03d', $this->projectNumber++));

        $this->entityManager->persist($project);
        $this->entityManager->flush();

        return $project;
    }

    protected function createTestActivity(Project $project, User $assignee, string $title = 'Attività Test'): Activity
    {
        $activity = (new Activity())
            ->setProject($project)
            ->setAssignee($assignee)
            ->setCreatedBy($assignee)
            ->setTitle($title);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $activity;
    }

    protected function createTestTimeEntry(
        Activity $activity,
        User $user,
        string $startedAt,
        ?string $endedAt,
        string $description = 'Lavoro di test',
        bool $billable = true,
    ): TimeEntry {
        $entry = (new TimeEntry())
            ->setActivity($activity)
            ->setUser($user)
            ->setStartedAt(new DateTimeImmutable($startedAt))
            ->setEndedAt(null === $endedAt ? null : new DateTimeImmutable($endedAt))
            ->setDescription($description)
            ->setBillable($billable)
            ->applyRateSnapshot(5_000);

        $this->entityManager->persist($entry);
        $this->entityManager->flush();

        return $entry;
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        unset($this->entityManager, $this->client);
        parent::tearDown();
    }
}
