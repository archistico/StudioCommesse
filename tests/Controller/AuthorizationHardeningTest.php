<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Activity;
use App\Entity\Attachment;
use App\Entity\Payment;
use App\Entity\Project;
use App\Enum\AttachmentClassification;
use App\Enum\ProjectStatus;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;

final class AuthorizationHardeningTest extends DatabaseWebTestCase
{
    public function testCollaboratorCannotOpenPartnerOnlyAreasByDirectUrl(): void
    {
        $collaborator = $this->createUser('audit-collaboratore');
        $this->client->loginUser($collaborator);

        foreach (['/admin/utenti', '/economia', '/controllo', '/controllo/collaboratori/'.$collaborator->getId(), '/report/mensile', '/report/mensile/csv', '/report/mensile/utenti/csv', '/audit', '/audit/csv'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403, $path);
        }
    }

    public function testArchivedProjectIsReadOnlyAcrossActivitiesHoursPaymentsAndDocuments(): void
    {
        $partner = $this->createUser('audit-socio', UserRole::Partner);
        $project = $this->createProject($this->createCustomer('Cliente archivio audit'), $partner, status: ProjectStatus::Completed);
        $activity = $this->createTestActivity($project, $partner, 'Attività archivio audit');
        $entry = $this->createTestTimeEntry($activity, $partner, '2026-07-01 09:00:00', '2026-07-01 10:00:00');
        $payment = (new Payment())
            ->setProject($project)
            ->setRecordedBy($partner)
            ->setDescription('Incasso archivio audit')
            ->setAmountCents(10_000);
        $attachment = new Attachment(
            $project,
            $activity,
            $partner,
            AttachmentClassification::Technical,
            'audit.pdf',
            '2026/07/'.str_repeat('a', 32).'.pdf',
            'application/pdf',
            10,
            str_repeat('b', 64),
            'Documento audit',
        );
        $this->entityManager->persist($payment);
        $this->entityManager->persist($attachment);
        $this->entityManager->flush();
        $project->archive();
        $this->entityManager->flush();
        $activityId = $activity->getId();
        $entryId = $entry->getId();
        $paymentId = $payment->getId();
        $attachmentId = $attachment->getId();
        self::assertIsInt($activityId);
        self::assertIsInt($entryId);
        self::assertIsInt($paymentId);
        self::assertIsInt($attachmentId);

        $this->client->loginUser($partner);
        foreach ([
            '/attivita/'.$activityId.'/modifica',
            '/ore/'.$entryId.'/modifica',
            '/economia/commessa/'.$project->getId().'/incasso',
            '/economia/incasso/'.$paymentId.'/modifica',
        ] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403, $path);
        }

        $this->client->request('POST', '/economia/incasso/'.$paymentId.'/elimina', ['_token' => 'crafted']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/documenti/'.$attachmentId.'/elimina', ['_token' => 'crafted']);
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->client->request('GET', '/documenti/'.$attachmentId);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->selectButton('Salva modifiche'));
        self::assertCount(0, $crawler->selectButton('Elimina documento'));

        $crawler = $this->client->request('GET', '/commesse/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('a[href="/attivita/'.$activityId.'/modifica"]'));

        $crawler = $this->client->request('GET', '/ore/attivita/'.$activityId);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('a[href="/ore/'.$entryId.'/modifica"]'));

        $crawler = $this->client->request('GET', '/economia/commessa/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('a[href="/economia/incasso/'.$paymentId.'/modifica"]'));
    }

    public function testCollaboratorCannotMassAssignProjectEconomicFields(): void
    {
        $responsible = $this->createUser('audit-responsabile');
        $project = $this->createProject($this->createCustomer('Cliente mass assignment'), $responsible);
        $project->setEstimatedAmountCents(12_300)->setDefaultHourlyRateCents(4_500);
        $this->entityManager->flush();
        $this->client->loginUser($responsible);

        $crawler = $this->client->request('GET', '/commesse/'.$project->getId().'/modifica');
        $form = $crawler->selectButton('Salva modifiche')->form();
        $values = $form->getPhpValues();
        $projectValues = $values['project'] ?? null;
        if (!is_array($projectValues)) {
            self::fail('Valori del form commessa mancanti.');
        }
        $projectValues['estimatedAmountCents'] = '999999.99';
        $projectValues['defaultHourlyRateCents'] = '999.99';
        $projectValues['responsible'] = (string) $responsible->getId();
        $values['project'] = $projectValues;
        $this->client->request('POST', '/commesse/'.$project->getId().'/modifica', $values);

        self::assertResponseStatusCodeSame(422);
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Project::class, $project->getId());
        self::assertInstanceOf(Project::class, $reloaded);
        self::assertSame(12_300, $reloaded->getEstimatedAmountCents());
        self::assertSame(4_500, $reloaded->getDefaultHourlyRateCents());
    }

    public function testCollaboratorCannotMassAssignActivityHourlyRate(): void
    {
        $collaborator = $this->createUser('audit-attivita');
        $project = $this->createProject($this->createCustomer('Cliente attività protetta'), $collaborator);
        $activity = $this->createTestActivity($project, $collaborator, 'Attività tariffa protetta');
        $activity->setHourlyRateOverrideCents(5_000);
        $this->entityManager->flush();
        $activityId = $activity->getId();
        self::assertIsInt($activityId);
        $this->client->loginUser($collaborator);

        $crawler = $this->client->request('GET', '/attivita/'.$activityId.'/modifica');
        $form = $crawler->selectButton('Salva')->form();
        $values = $form->getPhpValues();
        $activityValues = $values['activity'] ?? null;
        if (!is_array($activityValues)) {
            self::fail('Valori del form attività mancanti.');
        }
        $activityValues['hourlyRateOverrideCents'] = '999.99';
        $values['activity'] = $activityValues;
        $this->client->request('POST', '/attivita/'.$activityId.'/modifica', $values);

        self::assertResponseStatusCodeSame(422);
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Activity::class, $activityId);
        self::assertInstanceOf(Activity::class, $reloaded);
        self::assertSame(5_000, $reloaded->getHourlyRateOverrideCents());
    }

    public function testResponsibleCollaboratorDoesNotSeeHourlyRatesOrCosts(): void
    {
        $responsible = $this->createUser('audit-costi-responsabile');
        $project = $this->createProject($this->createCustomer('Cliente costi protetti'), $responsible);
        $activity = $this->createTestActivity($project, $responsible, 'Attività costi protetti');
        $this->createTestTimeEntry($activity, $responsible, '2026-07-02 09:00:00', '2026-07-02 11:00:00');
        $activityId = $activity->getId();
        self::assertIsInt($activityId);
        $this->client->loginUser($responsible);

        $this->client->request('GET', '/ore/attivita/'.$activityId);
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('<th>Tariffa</th>', $content);
        self::assertStringNotContainsString('<th>Costo</th>', $content);
        self::assertStringNotContainsString('50,00', $content);
    }

    public function testPrivateAndFinancialProjectDataNeverReachUnrelatedCollaboratorMarkup(): void
    {
        $responsible = $this->createUser('audit-private-responsabile');
        $unrelated = $this->createUser('audit-private-estraneo');
        $partner = $this->createUser('audit-private-socio', UserRole::Partner);
        $project = $this->createProject($this->createCustomer('Cliente privacy'), $responsible);
        $project->setPrivateNote('SEGRETO-M9-2-B')->setEstimatedAmountCents(123_456);
        $this->entityManager->flush();

        $this->client->loginUser($unrelated);
        $this->client->request('GET', '/commesse/'.$project->getId());
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('SEGRETO-M9-2-B', $content);
        self::assertStringNotContainsString('Margine corrente', $content);
        self::assertStringNotContainsString('Da incassare', $content);

        $this->client->loginUser($responsible);
        $this->client->request('GET', '/commesse/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('SEGRETO-M9-2-B', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Margine corrente', (string) $this->client->getResponse()->getContent());

        $this->client->loginUser($partner);
        $this->client->request('GET', '/commesse/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('SEGRETO-M9-2-B', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Margine corrente', (string) $this->client->getResponse()->getContent());
    }
}
