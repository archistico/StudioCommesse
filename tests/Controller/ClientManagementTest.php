<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\Client;
use App\Enum\AuditAction;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;

final class ClientManagementTest extends DatabaseWebTestCase
{
    public function testCollaboratorCanReadClientsButCannotCreateThem(): void
    {
        $collaborator = $this->createUser('collaboratore');
        $this->createCustomer('Cliente Visibile');
        $this->client->loginUser($collaborator);

        $this->client->request('GET', '/clienti');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Cliente Visibile');

        $this->client->request('GET', '/clienti/nuovo');
        self::assertResponseStatusCodeSame(403);
    }

    public function testClientNameIsThePrimaryTableLinkWithoutAnActionColumn(): void
    {
        $collaborator = $this->createUser('lettore-clienti');
        $client = $this->createCustomer('Cliente Cliccabile');
        $this->client->loginUser($collaborator);

        $crawler = $this->client->request('GET', '/clienti');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('a[href="/clienti/%d"]', $client->getId()));
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<th>Azioni</th>', $content);
        self::assertStringNotContainsString('<th>Apri</th>', $content);
    }

    public function testPartnerCreatesClientThroughClassicFormAndAuditIsWritten(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/clienti/nuovo');
        $form = $crawler->selectButton('Crea cliente')->form([
            'client[name]' => 'Impresa Alfa',
            'client[contactPerson]' => 'Anna Bianchi',
            'client[email]' => 'amministrazione@alfa.test',
            'client[phone]' => '',
            'client[address]' => '',
            'client[taxCode]' => '',
            'client[vatNumber]' => 'IT01234567890',
            'client[notes]' => '',
        ]);
        $this->client->submit($form);

        self::assertResponseRedirects();
        $created = $this->entityManager->getRepository(Client::class)->findOneBy(['name' => 'Impresa Alfa']);
        self::assertInstanceOf(Client::class, $created);
        self::assertSame('amministrazione@alfa.test', $created->getEmail());

        $audit = $this->entityManager->getRepository(AuditLog::class)->findOneBy([
            'action' => AuditAction::ClientCreated,
            'subjectId' => $created->getId(),
        ]);
        self::assertInstanceOf(AuditLog::class, $audit);
    }

    public function testClientCannotBeArchivedWhileItHasNonArchivedProjects(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $client = $this->createCustomer('Cliente con commessa');
        $this->createProject($client, $partner, status: ProjectStatus::Completed);
        $this->client->loginUser($partner);
        $clientId = $client->getId();
        self::assertNotNull($clientId);

        $crawler = $this->client->request('GET', '/clienti/'.$clientId.'/modifica');
        $this->client->submit($crawler->selectButton('Archivia cliente')->form());

        self::assertResponseRedirects('/clienti/'.$clientId);
        $client = $this->entityManager->find(Client::class, $clientId);
        self::assertInstanceOf(Client::class, $client);
        self::assertFalse($client->isArchived());
    }

    public function testClientCanBeArchivedAfterAllProjectsAreArchived(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $client = $this->createCustomer('Cliente storico');
        $project = $this->createProject($client, $partner, status: ProjectStatus::Completed);
        $project->archive();
        $this->entityManager->flush();
        $this->client->loginUser($partner);
        $clientId = $client->getId();
        self::assertNotNull($clientId);

        $crawler = $this->client->request('GET', '/clienti/'.$clientId.'/modifica');
        $this->client->submit($crawler->selectButton('Archivia cliente')->form());

        self::assertResponseRedirects('/clienti/'.$clientId);
        $client = $this->entityManager->find(Client::class, $clientId);
        self::assertInstanceOf(Client::class, $client);
        self::assertTrue($client->isArchived());
    }

    public function testArchivedClientIsReadOnlyUntilRestored(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $client = $this->createCustomer('Cliente archiviato');
        $client->archive();
        $this->entityManager->flush();
        $this->client->loginUser($partner);

        $this->client->request('GET', '/clienti/'.$client->getId().'/modifica');

        self::assertResponseStatusCodeSame(403);
    }

}
