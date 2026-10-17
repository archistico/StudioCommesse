<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\Client;
use App\Entity\Project;
use App\Enum\AuditAction;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ClientWorkflowRegressionTest extends DatabaseWebTestCase
{
    #[DataProvider('searchExamples')]
    public function testClientSearchIsLiteralAndCaseInsensitiveForAccentedNames(
        string $field,
        string $matchingValue,
        string $otherValue,
        string $query,
    ): void {
        $user = $this->createUser('lettore-ricerca-clienti');
        $matching = $this->createCustomer('Cliente corrispondente');
        $other = $this->createCustomer('Cliente escluso');
        match ($field) {
            'contactPerson' => [$matching->setContactPerson($matchingValue), $other->setContactPerson($otherValue)],
            'email' => [$matching->setEmail($matchingValue), $other->setEmail($otherValue)],
            default => [$matching->setName($matchingValue), $other->setName($otherValue)],
        };
        $this->entityManager->flush();
        $matchingId = $matching->getId();
        $otherId = $other->getId();
        $this->client->loginUser($user);

        $this->client->request('GET', '/clienti', ['q' => $query]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('tr[data-client-id="'.$matchingId.'"]');
        self::assertSelectorNotExists('tr[data-client-id="'.$otherId.'"]');
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function searchExamples(): iterable
    {
        yield 'accento maiuscolo nella ragione sociale' => ['name', 'CITTÀ SERVIZI', 'Citta Servizi', 'città'];
        yield 'accento maiuscolo nel referente' => ['contactPerson', 'NOÈ BIANCHI', 'Noe Bianchi', 'noè'];
        yield 'percentuale letterale' => ['name', 'Studio 100% Verde', 'Studio 1000 Verde', '100%'];
        yield 'underscore letterale nella email' => ['email', 'studio_alfa@example.test', 'studioXalfa@example.test', 'studio_alfa'];
        yield 'escape letterale' => ['name', 'Studio!_A', 'Studio!XA', '!_A'];
        yield 'ricerca maiuscola con accento' => ['name', 'città servizi', 'citta servizi', 'CITTÀ'];
    }

    #[DataProvider('archiveCommands')]
    public function testRepeatedArchiveCommandsDoNotDuplicateAuditEvents(bool $restore): void
    {
        $partner = $this->createUser('socio-comandi-clienti', UserRole::Partner);
        $customer = $this->createCustomer();
        if ($restore) {
            $customer->archive();
            $this->entityManager->flush();
        }
        $customerId = $customer->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/clienti/'.$customerId.($restore ? '' : '/modifica'));
        $form = $crawler->selectButton($restore ? 'Ripristina' : 'Archivia cliente')->form();
        $path = '/clienti/'.$customerId.($restore ? '/ripristina' : '/archivia');
        $values = $form->getPhpValues();
        $this->client->request('POST', $path, $values);
        self::assertResponseRedirects('/clienti/'.$customerId);
        $this->client->request('POST', $path, $values);
        self::assertResponseRedirects('/clienti/'.$customerId);

        $this->entityManager->clear();
        $saved = $this->entityManager->find(Client::class, $customerId);
        self::assertInstanceOf(Client::class, $saved);
        self::assertSame(!$restore, $saved->isArchived());
        self::assertSame(1, $this->entityManager->getRepository(AuditLog::class)->count([
            'action' => $restore ? AuditAction::ClientRestored : AuditAction::ClientArchived,
            'subjectId' => $customerId,
        ]));
    }

    /** @return iterable<string, array{bool}> */
    public static function archiveCommands(): iterable
    {
        yield 'archiviazione ripetuta' => [false];
        yield 'ripristino ripetuto' => [true];
    }

    public function testNewProjectFromClientPageKeepsTheSelectedClient(): void
    {
        $partner = $this->createUser('socio-commessa-cliente', UserRole::Partner);
        $this->createCustomer('Azienda prima in elenco');
        $customer = $this->createCustomer('Zeta cliente corretto');
        $customerId = $customer->getId();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/clienti/'.$customerId);
        $crawler = $this->client->click($crawler->selectLink('Nuova commessa')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="project[client]"] option[value="'.$customerId.'"][selected]');
        $this->client->submit($crawler->selectButton('Crea commessa')->form([
            'project[name]' => 'Commessa dal cliente',
            'project[responsible]' => (string) $partner->getId(),
        ]));

        self::assertResponseRedirects();
        $this->entityManager->clear();
        $created = $this->entityManager->getRepository(Project::class)->findOneBy(['name' => 'Commessa dal cliente']);
        self::assertInstanceOf(Project::class, $created);
        self::assertSame($customerId, $created->getClient()?->getId());
    }

    #[DataProvider('archiveVisibility')]
    public function testSearchRespectsTheArchivedClientFilter(bool $includeArchived): void
    {
        $user = $this->createUser('lettore-archivi-clienti');
        $active = $this->createCustomer('CITTÀ Attiva');
        $archived = $this->createCustomer('CITTÀ Storica');
        $archived->archive();
        $this->entityManager->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/clienti', ['q' => 'città', 'archiviati' => $includeArchived ? '1' : '0']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('tr[data-client-id="'.$active->getId().'"]');
        self::assertSelectorCount($includeArchived ? 1 : 0, 'tr[data-client-id="'.$archived->getId().'"]');
    }

    /** @return iterable<string, array{bool}> */
    public static function archiveVisibility(): iterable
    {
        yield 'solo attivi' => [false];
        yield 'includi archiviati' => [true];
    }

    #[DataProvider('invalidClientContexts')]
    public function testNewProjectRejectsAnInvalidClientContext(string $context, int $status): void
    {
        $partner = $this->createUser('socio-contesto-invalido', UserRole::Partner);
        $customer = $this->createCustomer();
        $customer->archive();
        $this->entityManager->flush();
        $this->client->loginUser($partner);

        $this->client->request('GET', '/commesse/nuova', [
            'client' => 'archived' === $context ? (string) $customer->getId() : $context,
        ]);

        self::assertResponseStatusCodeSame($status);
        self::assertSame(0, $this->entityManager->getRepository(Project::class)->count([]));
    }

    /** @return iterable<string, array{string, int}> */
    public static function invalidClientContexts(): iterable
    {
        yield 'cliente archiviato' => ['archived', 403];
        yield 'cliente inesistente' => ['2147483647', 404];
        yield 'identificativo non numerico' => ['invalid', 400];
    }

    public function testNewProjectWithoutContextKeepsTheClientSelectionEmpty(): void
    {
        $partner = $this->createUser('socio-senza-contesto', UserRole::Partner);
        $this->createCustomer();
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/commesse/nuova');

        self::assertResponseIsSuccessful();
        self::assertSame('', $crawler->selectButton('Crea commessa')->form()['project[client]']->getValue());
    }

    #[DataProvider('archiveCommands')]
    public function testNoOpArchiveCommandsStillRequireAValidCsrfToken(bool $restore): void
    {
        $partner = $this->createUser('socio-csrf-archivi', UserRole::Partner);
        $customer = $this->createCustomer();
        if (!$restore) {
            $customer->archive();
            $this->entityManager->flush();
        }
        $this->client->loginUser($partner);

        $this->client->request('POST', '/clienti/'.$customer->getId().($restore ? '/ripristina' : '/archivia'), [
            '_token' => 'invalid',
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
