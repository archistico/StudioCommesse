<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\Expense;
use App\Entity\Payment;
use App\Enum\AuditAction;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class MonthlyReportTest extends DatabaseWebTestCase
{
    public function testPartnerSeesMonthlyWorkProjectAndActionSummaries(): void
    {
        $month = new DateTimeImmutable('first day of this month midnight');
        $partner = $this->createUser('socio-report-mensile', UserRole::Partner);
        $worker = $this->createUser('worker-report-mensile');
        $project = $this->createProject($this->createCustomer('Cliente Report Mensile'), $worker, 'Commessa report mensile');
        $activity = $this->createTestActivity($project, $worker, 'Verifica mensile');
        $this->createTestTimeEntry(
            $activity,
            $worker,
            $month->modify('+2 days 09:00')->format('Y-m-d H:i:s'),
            $month->modify('+2 days 11:30')->format('Y-m-d H:i:s'),
            'Sopralluogo e verifica elaborati',
        );
        $this->createTestTimeEntry(
            $activity,
            $worker,
            $month->modify('-1 day 09:00')->format('Y-m-d H:i:s'),
            $month->modify('-1 day 10:00')->format('Y-m-d H:i:s'),
            'Fuori mese',
        );
        $expense = (new Expense())
            ->setProject($project)
            ->setRecordedBy($worker)
            ->setSpentOn($month->modify('+3 days'))
            ->setCategory('Viaggio')
            ->setDescription('Trasferta mensile')
            ->setAmountCents(15_000);
        $payment = (new Payment())
            ->setProject($project)
            ->setRecordedBy($partner)
            ->setPaidOn($month->modify('+4 days'))
            ->setDescription('Acconto mensile')
            ->setAmountCents(60_000);
        $audit = new AuditLog(
            AuditAction::ActivityUpdated,
            $partner->getUserIdentifier(),
            \App\Entity\Activity::class,
            $activity->getId(),
            ['project' => $project->getCode(), 'title' => $activity->getTitle()],
        );
        $this->entityManager->persist($expense);
        $this->entityManager->persist($payment);
        $this->entityManager->persist($audit);
        $this->entityManager->flush();

        $this->client->loginUser($partner);
        $this->client->request('GET', '/report/mensile?month='.$month->format('Y-m'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2.page-title', $month->format('m/Y'));
        self::assertSelectorTextContains('body', 'Sopralluogo e verifica elaborati');
        self::assertStringNotContainsString('Fuori mese', (string) $this->client->getResponse()->getContent());
        self::assertSelectorTextContains('body', 'Commessa report mensile');
        self::assertSelectorTextContains('body', '2:30');
        self::assertSelectorTextContains('body', '150,00');
        self::assertSelectorTextContains('body', '600,00');
        self::assertSelectorTextContains('body', 'Attività aggiornata');
        self::assertSelectorExists('a[href*="/report/mensile/csv"]');
    }

    public function testMonthlyReportIsPartnerOnlyAndCsvContainsDetailedEntries(): void
    {
        $month = new DateTimeImmutable('first day of this month midnight');
        $partner = $this->createUser('socio-csv', UserRole::Partner);
        $collaborator = $this->createUser('collaboratore-no-report');
        $project = $this->createProject($this->createCustomer('Cliente CSV'), $collaborator, 'Commessa CSV');
        $activity = $this->createTestActivity($project, $collaborator, 'Attività CSV');
        $this->createTestTimeEntry(
            $activity,
            $collaborator,
            $month->modify('+1 day 08:00')->format('Y-m-d H:i:s'),
            $month->modify('+1 day 09:00')->format('Y-m-d H:i:s'),
            'Dettaglio CSV',
        );

        $this->client->loginUser($collaborator);
        $this->client->request('GET', '/report/mensile?month='.$month->format('Y-m'));
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($partner);
        $this->client->request('GET', '/report/mensile/csv?month='.$month->format('Y-m').'&project='.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('Dettaglio CSV', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Commessa CSV', (string) $this->client->getResponse()->getContent());
    }
    public function testMonthlyReportSummarizesCompletedHoursAndBothUserCostModels(): void
    {
        $month = new DateTimeImmutable('first day of this month midnight');
        $partner = $this->createUser('socio-riepilogo', UserRole::Partner);
        $worker = $this->createUser('utente-riepilogo');
        $worker->setDefaultHourlyRateCents(3_000);
        $inactiveWorker = $this->createUser('utente-disattivato', UserRole::Collaborator, false);
        $inactiveWorker->setDefaultHourlyRateCents(2_500);
        $project = $this->createProject($this->createCustomer('Cliente Riepilogo'), $worker, 'Commessa riepilogo utenti');
        $activity = $this->createTestActivity($project, $worker, 'Attività riepilogo');
        $completed = $this->createTestTimeEntry(
            $activity,
            $worker,
            $month->modify('+2 days 09:00')->format('Y-m-d H:i:s'),
            $month->modify('+2 days 10:30')->format('Y-m-d H:i:s'),
            'Costo con override storico',
        );
        $completed->applyRateSnapshot(4_000);
        $inactiveEntry = $this->createTestTimeEntry(
            $activity,
            $inactiveWorker,
            $month->modify('+3 days 09:00')->format('Y-m-d H:i:s'),
            $month->modify('+3 days 10:00')->format('Y-m-d H:i:s'),
            'Lavoro precedente alla disattivazione',
        );
        $inactiveEntry->applyRateSnapshot(5_000);
        $this->createTestTimeEntry(
            $activity,
            $worker,
            $month->modify('+4 days 09:00')->format('Y-m-d H:i:s'),
            null,
            'Timer ancora aperto',
        );
        $this->entityManager->flush();

        $this->client->loginUser($partner);
        $this->client->request('GET', '/report/mensile?month='.$month->format('Y-m'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-monthly-user-cost-summary]', 'Utente-riepilogo');
        self::assertSelectorTextContains('[data-monthly-user-cost-summary]', '1:30');
        self::assertSelectorTextContains('[data-monthly-user-cost-summary]', '€ 30,00/h');
        self::assertSelectorTextContains('[data-monthly-user-cost-summary]', '€ 45,00');
        self::assertSelectorTextContains('[data-monthly-user-cost-summary]', '€ 60,00');
        self::assertSelectorTextContains('[data-monthly-user-cost-summary]', '€ 15,00');
        self::assertSelectorTextContains('[data-monthly-user-cost-summary]', 'Utente-disattivato');
        self::assertSelectorTextContains('[data-monthly-user-cost-summary]', 'Utente disattivato');
        self::assertSelectorTextNotContains('[data-monthly-user-cost-summary]', 'Timer ancora aperto');
        self::assertSelectorExists('a[href*="/report/mensile/utenti/csv"]');
    }

    public function testMonthlyUserSummaryRespectsProjectFilterAndMarksMissingStandardRate(): void
    {
        $month = new DateTimeImmutable('first day of this month midnight');
        $partner = $this->createUser('socio-filtro-utenti', UserRole::Partner);
        $workerWithoutRate = $this->createUser('senza-tariffa');
        $otherWorker = $this->createUser('fuori-filtro-utenti');
        $selectedProject = $this->createProject($this->createCustomer('Cliente Filtro Utenti'), $workerWithoutRate, 'Commessa inclusa riepilogo');
        $otherProject = $this->createProject($this->createCustomer('Altro Cliente Utenti'), $otherWorker, 'Commessa esclusa riepilogo');
        $selectedActivity = $this->createTestActivity($selectedProject, $workerWithoutRate, 'Attività inclusa riepilogo');
        $otherActivity = $this->createTestActivity($otherProject, $otherWorker, 'Attività esclusa riepilogo');
        $this->createTestTimeEntry($selectedActivity, $workerWithoutRate, $month->modify('+1 day 08:00')->format('Y-m-d H:i:s'), $month->modify('+1 day 09:00')->format('Y-m-d H:i:s'));
        $this->createTestTimeEntry($otherActivity, $otherWorker, $month->modify('+1 day 10:00')->format('Y-m-d H:i:s'), $month->modify('+1 day 11:00')->format('Y-m-d H:i:s'));

        $this->client->loginUser($partner);
        $this->client->request('GET', '/report/mensile?month='.$month->format('Y-m').'&project='.$selectedProject->getId());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-monthly-user-cost-summary]', 'Senza-tariffa');
        self::assertSelectorTextContains('[data-monthly-user-cost-summary]', 'Non impostata');
        self::assertSelectorTextNotContains('[data-monthly-user-cost-summary]', 'Fuori-filtro-utenti');
    }

    public function testMonthlyUserSummaryCsvIsSeparateAndPartnerOnly(): void
    {
        $month = new DateTimeImmutable('first day of this month midnight');
        $partner = $this->createUser('socio-csv-utenti', UserRole::Partner);
        $worker = $this->createUser('collaboratore-csv-utenti');
        $worker->setDefaultHourlyRateCents(3_500);
        $project = $this->createProject($this->createCustomer('Cliente CSV Utenti'), $worker, 'Commessa CSV utenti');
        $activity = $this->createTestActivity($project, $worker, 'Attività CSV utenti');
        $this->createTestTimeEntry($activity, $worker, $month->modify('+1 day 08:00')->format('Y-m-d H:i:s'), $month->modify('+1 day 10:00')->format('Y-m-d H:i:s'));
        $this->entityManager->flush();

        $this->client->loginUser($worker);
        $this->client->request('GET', '/report/mensile/utenti/csv?month='.$month->format('Y-m'));
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($partner);
        $this->client->request('GET', '/report/mensile/utenti/csv?month='.$month->format('Y-m').'&project='.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/csv; charset=UTF-8');
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Costo standard teorico euro', $content);
        self::assertStringContainsString('Costo storico effettivo euro', $content);
        self::assertStringContainsString('Collaboratore-csv-utenti', $content);
        self::assertStringContainsString('70,00', $content);
        self::assertStringContainsString('100,00', $content);
    }

}
