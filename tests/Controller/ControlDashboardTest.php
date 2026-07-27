<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;

final class ControlDashboardTest extends DatabaseWebTestCase
{
    public function testControlAreaIsRestrictedToPartners(): void
    {
        $collaborator = $this->createUser('controllo-negato');
        $this->client->loginUser($collaborator);
        $this->client->request('GET', '/controllo');
        self::assertResponseStatusCodeSame(403);

        $partner = $this->createUser('controllo-consentito', UserRole::Partner);
        $this->client->loginUser($partner);
        $this->client->request('GET', '/controllo');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2.page-title', 'Controllo');
        self::assertSelectorTextContains('a.nav-link[href="/controllo"]', 'Controllo');
        self::assertSelectorExists('a[href^="/controllo/collaboratori/"]');
    }

    public function testFiltersAndSortingPersistInTheSessionUntilReset(): void
    {
        $partner = $this->createUser('filtri-controllo', UserRole::Partner);
        $client = $this->createCustomer('Cliente filtri persistenti');
        $this->createProject($client, $partner, 'Commessa filtri');
        $this->client->loginUser($partner);

        $this->client->request('GET', sprintf('/controllo?client=%d&from=2026-01-01&to=2026-07-31&sort=code&direction=asc', $client->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('select[name="client"] option[value="%d"][selected]', $client->getId()));
        self::assertSelectorExists('select[name="sort"] option[value="code"][selected]');
        self::assertSelectorExists('select[name="direction"] option[value="asc"][selected]');

        $this->client->request('GET', '/controllo');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists(sprintf('select[name="client"] option[value="%d"][selected]', $client->getId()));
        self::assertSelectorExists('input[name="from"][value="2026-01-01"]');
    }

    public function testProjectClosurePanelIsVisibleOnlyToPartners(): void
    {
        $partner = $this->createUser('pannello-socio', UserRole::Partner);
        $collaborator = $this->createUser('pannello-collaboratore');
        $project = $this->createProject($this->createCustomer('Cliente pannello'), $partner, 'Commessa pannello');

        $this->client->loginUser($collaborator);
        $this->client->request('GET', '/commesse/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('Controllo chiusura', (string) $this->client->getResponse()->getContent());

        $this->client->loginUser($partner);
        $this->client->request('GET', '/commesse/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Controllo chiusura');
        self::assertSelectorTextContains('body', 'Preventivo mancante');
    }

    public function testPartnerCanInspectDayByDayWorkWhileCollaboratorCannot(): void
    {
        $partner = $this->createUser('valutazione-socio', UserRole::Partner);
        $worker = $this->createUser('valutazione-collaboratore');
        $project = $this->createProject($this->createCustomer('Cliente valutazione'), $partner, 'Commessa valutazione');
        $activity = $this->createTestActivity($project, $worker, 'Verifica strutturale');
        $this->createTestTimeEntry($activity, $worker, '2026-07-10 08:00:00', '2026-07-10 10:00:00', 'Sopralluogo e rilievi');
        $this->createTestTimeEntry($activity, $worker, '2026-07-10 10:30:00', '2026-07-10 12:00:00', 'Aggiornamento elaborati', false);
        $this->createTestTimeEntry($activity, $worker, '2026-07-11 09:00:00', '2026-07-11 10:00:00', 'Relazione tecnica');

        $this->client->loginUser($worker);
        $this->client->request('GET', sprintf('/controllo/collaboratori/%d?from=2026-07-01&to=2026-07-31', $worker->getId()));
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($partner);
        $this->client->request('GET', sprintf('/controllo/collaboratori/%d?from=2026-07-01&to=2026-07-31', $worker->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2.page-title', 'Valutazione collaboratore');
        self::assertSelectorTextContains('body', 'Sopralluogo e rilievi');
        self::assertSelectorTextContains('body', 'Aggiornamento elaborati');
        self::assertSelectorTextContains('body', 'Relazione tecnica');
        self::assertSelectorTextContains('body', '10/07/2026');
        self::assertSelectorTextContains('body', '11/07/2026');
        self::assertSelectorTextContains('body', '4:30');
        self::assertSelectorTextContains('body', 'Dettaglio giornaliero');
    }
}
