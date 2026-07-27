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
}
