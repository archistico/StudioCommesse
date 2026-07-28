<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\ActivityPriority;
use App\Enum\ActivityStatus;
use App\Enum\ProjectPriority;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class DashboardUiHotfixTest extends DatabaseWebTestCase
{
    public function testDashboardShowsOperationalCardsRecentActivitiesAndRecentHours(): void
    {
        $user = $this->createUser('dashboard');
        $client = $this->createCustomer('Cliente Dashboard');
        $project = $this->createProject($client, $user, 'Commessa Dashboard');

        $openActivity = $this->createTestActivity($project, $user, 'Attività Dashboard Aperta')
            ->setPriority(ActivityPriority::High)
            ->setInitialEstimatedMinutes(180)
            ->setRemainingEstimatedMinutes(120);
        $closedActivity = $this->createTestActivity($project, $user, 'Attività Dashboard Chiusa')
            ->setStatus(ActivityStatus::Completed)
            ->setInitialEstimatedMinutes(240);
        $this->entityManager->flush();

        $currentMonth = new DateTimeImmutable('first day of this month midnight');
        $currentEntryStartedAt = $currentMonth->modify('+2 days 09:00');
        $previousEntryStartedAt = $currentMonth->modify('-1 day 09:00');

        $this->createTestTimeEntry(
            $openActivity,
            $user,
            $currentEntryStartedAt->format('Y-m-d H:i:s'),
            $currentEntryStartedAt->modify('+90 minutes')->format('Y-m-d H:i:s'),
            'Voce conclusa dashboard',
        );
        $this->createTestTimeEntry(
            $openActivity,
            $user,
            $previousEntryStartedAt->format('Y-m-d H:i:s'),
            $previousEntryStartedAt->modify('+120 minutes')->format('Y-m-d H:i:s'),
            'Voce mese precedente esclusa',
        );
        $this->createTestTimeEntry(
            $closedActivity,
            $user,
            $currentEntryStartedAt->modify('+2 hours')->format('Y-m-d H:i:s'),
            null,
            'Timer ancora attivo',
        );

        $this->client->loginUser($user);
        $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-dashboard-operational-summary]');
        self::assertSelectorExists('[data-worked-minutes="90"]');
        self::assertSelectorTextContains('body', 'Commesse in attesa');
        self::assertSelectorTextContains('body', 'Commesse in ritardo');
        self::assertSelectorTextContains('body', 'Ore effettuate');
        self::assertSelectorTextContains('[data-worked-minutes="90"]', '1:30 Ore effettuate');
        self::assertSelectorTextContains('[data-worked-minutes="90"] + .text-secondary', 'Registrazioni concluse');
        self::assertStringNotContainsString('Ore pianificate', (string) $this->client->getResponse()->getContent());
        self::assertSelectorTextContains('body', 'Commesse aggiornate di recente');
        self::assertSelectorTextContains('body', 'Attività aggiornate di recente');
        self::assertSelectorTextContains('body', 'Ore aggiornate di recente');
        self::assertSelectorTextContains('body', 'Attività Dashboard Aperta');
        self::assertSelectorTextContains('body', 'Voce conclusa dashboard');
        self::assertStringNotContainsString('Quadro operativo', (string) $this->client->getResponse()->getContent());
    }

    public function testActivityIndexDefaultsToCurrentUserAndAutoSubmitsAssigneeChanges(): void
    {
        $currentUser = $this->createUser('corrente');
        $otherUser = $this->createUser('altro');
        $client = $this->createCustomer('Cliente Attività Personali');
        $project = $this->createProject($client, $currentUser, 'Commessa Attività Personali');
        $this->createTestActivity($project, $currentUser, 'Attività personale visibile');
        $this->createTestActivity($project, $otherUser, 'Attività altrui nascosta');

        $this->client->loginUser($currentUser);
        $this->client->request('GET', '/attivita');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Attività personale visibile');
        self::assertStringNotContainsString('Attività altrui nascosta', (string) $this->client->getResponse()->getContent());
        self::assertSelectorExists('select[name="assignee"][onchange="this.form.requestSubmit()"]');
        self::assertSelectorExists('option[value="me"][selected]');
        self::assertSelectorTextContains('#assignee-help', 'mostrate subito le tue attività');
    }

    public function testProjectPrioritiesAreRenderedAsAccessibleIcons(): void
    {
        $user = $this->createUser('priorita');
        $client = $this->createCustomer('Cliente Priorità');
        $this->createProject($client, $user, 'Bassa', priority: ProjectPriority::Low);
        $this->createProject($client, $user, 'Normale', priority: ProjectPriority::Normal);
        $this->createProject($client, $user, 'Alta', priority: ProjectPriority::High);
        $this->createProject($client, $user, 'Urgente', priority: ProjectPriority::Urgent);

        $this->client->loginUser($user);
        $this->client->request('GET', '/commesse');

        self::assertResponseIsSuccessful();
        foreach (['low', 'normal', 'high', 'urgent'] as $priority) {
            self::assertSelectorExists(sprintf('td[data-priority="%s"] svg', $priority));
        }
        self::assertSelectorExists('[aria-label="Priorità Bassa"]');
        self::assertSelectorExists('[aria-label="Priorità Normale"]');
        self::assertSelectorExists('[aria-label="Priorità Alta"]');
        self::assertSelectorExists('[aria-label="Priorità Urgente"]');
    }
}
