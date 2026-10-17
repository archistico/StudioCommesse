<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Project;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;

final class ProjectWorkflowRegressionTest extends DatabaseWebTestCase
{
    #[DataProvider('validWorkflows')]
    public function testValidWorkflowDatesAndEmptyFinancialFieldsCanBeSaved(
        ProjectStatus $status,
        string $startDate,
        string $dueDate,
        ?string $expectedStartDate,
    ): void {
        $partner = $this->createUser('socio-workflow', UserRole::Partner);
        $customer = $this->createCustomer();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/commesse/nuova');
        $this->client->submit($crawler->selectButton('Crea commessa')->form([
            'project[name]' => 'Workflow valido',
            'project[client]' => (string) $customer->getId(),
            'project[responsible]' => (string) $partner->getId(),
            'project[status]' => $status->value,
            'project[startDate]' => $startDate,
            'project[dueDate]' => $dueDate,
            'project[estimatedAmountCents]' => '',
            'project[defaultHourlyRateCents]' => '',
        ]));

        self::assertResponseRedirects();
        $this->entityManager->clear();
        $saved = $this->entityManager->getRepository(Project::class)->findOneBy(['name' => 'Workflow valido']);
        self::assertInstanceOf(Project::class, $saved);
        self::assertSame($status, $saved->getStatus());
        self::assertSame($expectedStartDate, $saved->getStartDate()?->format('Y-m-d'));
        self::assertSame($dueDate, $saved->getDueDate()?->format('Y-m-d'));
        self::assertSame(0, $saved->getEstimatedAmountCents());
        self::assertSame(0, $saved->getDefaultHourlyRateCents());
    }

    /** @return iterable<string, array{ProjectStatus, string, string, ?string}> */
    public static function validWorkflows(): iterable
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
        $tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');

        yield 'inizio automatico' => [ProjectStatus::InProgress, '', $tomorrow, $today];
        yield 'inizio storico esplicito' => [ProjectStatus::InProgress, $yesterday, $yesterday, $yesterday];
        yield 'inizio ignorato se non iniziata' => [ProjectStatus::NotStarted, $tomorrow, $yesterday, null];
        yield 'in attesa senza inizio' => [ProjectStatus::Waiting, '', $yesterday, null];
    }

    public function testAutomaticStartDateIsValidatedBeforeCreatingProject(): void
    {
        $partner = $this->createUser('socio-date', UserRole::Partner);
        $customer = $this->createCustomer();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/commesse/nuova');
        $this->client->submit($crawler->selectButton('Crea commessa')->form([
            'project[name]' => 'Date incoerenti',
            'project[client]' => (string) $customer->getId(),
            'project[responsible]' => (string) $partner->getId(),
            'project[status]' => ProjectStatus::InProgress->value,
            'project[startDate]' => '',
            'project[dueDate]' => (new DateTimeImmutable('yesterday'))->format('Y-m-d'),
        ]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'La data prevista di fine non può precedere la data di inizio.');
        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Project::class)->findOneBy(['name' => 'Date incoerenti']));
    }

    public function testAutomaticStartDateIsValidatedBeforeUpdatingProject(): void
    {
        $partner = $this->createUser('socio-date-edit', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner);
        $projectId = $project->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/commesse/'.$projectId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva modifiche')->form([
            'project[status]' => ProjectStatus::InProgress->value,
            'project[startDate]' => '',
            'project[dueDate]' => (new DateTimeImmutable('yesterday'))->format('Y-m-d'),
        ]));

        self::assertResponseStatusCodeSame(422);
        $this->entityManager->clear();
        $saved = $this->entityManager->find(Project::class, $projectId);
        self::assertInstanceOf(Project::class, $saved);
        self::assertSame(ProjectStatus::NotStarted, $saved->getStatus());
        self::assertNull($saved->getStartDate());
        self::assertNull($saved->getDueDate());
    }

    public function testClearingOptionalFinancialFieldsResetsTheirValues(): void
    {
        $partner = $this->createUser('socio-importi', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner);
        $project->setEstimatedAmountCents(125_000)->setDefaultHourlyRateCents(5_000);
        $this->entityManager->flush();
        $projectId = $project->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/commesse/'.$projectId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva modifiche')->form([
            'project[estimatedAmountCents]' => '',
            'project[defaultHourlyRateCents]' => '',
        ]));

        self::assertResponseRedirects('/commesse/'.$projectId);
        $this->entityManager->clear();
        $saved = $this->entityManager->find(Project::class, $projectId);
        self::assertInstanceOf(Project::class, $saved);
        self::assertSame(0, $saved->getEstimatedAmountCents());
        self::assertSame(0, $saved->getDefaultHourlyRateCents());
    }

    public function testPositiveFinancialValuesAreSavedInCents(): void
    {
        $partner = $this->createUser('socio-decimali', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner);
        $projectId = $project->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/commesse/'.$projectId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva modifiche')->form([
            'project[estimatedAmountCents]' => '1250,50',
            'project[defaultHourlyRateCents]' => '50,25',
        ]));

        self::assertResponseRedirects('/commesse/'.$projectId);
        $this->entityManager->clear();
        $saved = $this->entityManager->find(Project::class, $projectId);
        self::assertInstanceOf(Project::class, $saved);
        self::assertSame(125_050, $saved->getEstimatedAmountCents());
        self::assertSame(5_025, $saved->getDefaultHourlyRateCents());
    }

    #[DataProvider('financialFields')]
    public function testNegativeFinancialValueIsRejectedWithoutChangingStoredAmounts(string $field): void
    {
        $partner = $this->createUser('socio-negativi', UserRole::Partner);
        $project = $this->createProject($this->createCustomer(), $partner);
        $project->setEstimatedAmountCents(125_000)->setDefaultHourlyRateCents(5_000);
        $this->entityManager->flush();
        $projectId = $project->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/commesse/'.$projectId.'/modifica');
        $this->client->submit($crawler->selectButton('Salva modifiche')->form([
            'project['.$field.']' => '-12,50',
        ]));

        self::assertResponseStatusCodeSame(422);
        $this->entityManager->clear();
        $saved = $this->entityManager->find(Project::class, $projectId);
        self::assertInstanceOf(Project::class, $saved);
        self::assertSame(125_000, $saved->getEstimatedAmountCents());
        self::assertSame(5_000, $saved->getDefaultHourlyRateCents());
    }

    /** @return iterable<string, array{string}> */
    public static function financialFields(): iterable
    {
        yield 'preventivo' => ['estimatedAmountCents'];
        yield 'tariffa' => ['defaultHourlyRateCents'];
    }
}
