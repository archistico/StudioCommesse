<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Expense;
use App\Entity\Payment;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;

final class EconomicsVisibilityTest extends DatabaseWebTestCase
{
    public function testPartnerSeesAllExpensesAndPaymentsWhileCollaboratorSeesOnlyOwnExpenses(): void
    {
        $partner = $this->createUser('socio-economia', UserRole::Partner);
        $alice = $this->createUser('alice-economia');
        $bob = $this->createUser('bob-economia');
        $project = $this->createProject($this->createCustomer('Cliente Economia'), $bob, 'Commessa economia');
        $project->setEstimatedAmountCents(500_000);
        $this->entityManager->flush();

        $aliceExpense = (new Expense())
            ->setProject($project)
            ->setRecordedBy($alice)
            ->setSpentOn(new DateTimeImmutable('today'))
            ->setCategory('Viaggio')
            ->setDescription('Trasferta Alice')
            ->setAmountCents(12_300);
        $bobExpense = (new Expense())
            ->setProject($project)
            ->setRecordedBy($bob)
            ->setSpentOn(new DateTimeImmutable('today'))
            ->setCategory('Materiali')
            ->setDescription('Materiali Bob')
            ->setAmountCents(45_600);
        $payment = (new Payment())
            ->setProject($project)
            ->setRecordedBy($partner)
            ->setPaidOn(new DateTimeImmutable('today'))
            ->setDescription('Acconto riservato')
            ->setAmountCents(100_000);
        $this->entityManager->persist($aliceExpense);
        $this->entityManager->persist($bobExpense);
        $this->entityManager->persist($payment);
        $this->entityManager->flush();

        $this->client->loginUser($partner);
        $this->client->request('GET', '/economia/commessa/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Trasferta Alice');
        self::assertSelectorTextContains('body', 'Materiali Bob');
        self::assertSelectorTextContains('body', 'Acconto riservato');
        self::assertSelectorTextContains('body', 'Preventivo');

        $this->client->loginUser($alice);
        $this->client->request('GET', '/economia/commessa/'.$project->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Le mie spese');
        self::assertSelectorTextContains('body', 'Trasferta Alice');
        self::assertStringNotContainsString('Materiali Bob', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Acconto riservato', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Margine gestionale', (string) $this->client->getResponse()->getContent());
        self::assertStringNotContainsString('Da incassare', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/economia/spesa/'.$aliceExpense->getId().'/modifica');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/economia/spesa/'.$bobExpense->getId().'/modifica');
        self::assertResponseStatusCodeSame(403);
    }

    public function testCollaboratorCanOpenNewExpenseButNeverPaymentForms(): void
    {
        $collaborator = $this->createUser('collaboratore-spese');
        $project = $this->createProject($this->createCustomer('Cliente Spese'), $collaborator);
        $this->client->loginUser($collaborator);

        $this->client->request('GET', '/economia/commessa/'.$project->getId().'/spesa');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2.page-title', 'Nuova spesa');

        $this->client->request('GET', '/economia/commessa/'.$project->getId().'/incasso');
        self::assertResponseStatusCodeSame(403);
    }

    public function testPartnerSeesAmountsDueGroupedByClient(): void
    {
        $partner = $this->createUser('socio-dovuti-clienti', UserRole::Partner);
        $responsible = $this->createUser('responsabile-dovuti-clienti');
        $firstClient = $this->createCustomer('Cliente Alfa Dovuti');
        $secondClient = $this->createCustomer('Cliente Beta Dovuti');

        $firstProject = $this->createProject($firstClient, $responsible, 'Prima commessa Alfa');
        $firstProject->setEstimatedAmountCents(100_000);
        $secondProject = $this->createProject($firstClient, $responsible, 'Seconda commessa Alfa');
        $secondProject->setEstimatedAmountCents(50_000);
        $thirdProject = $this->createProject($secondClient, $responsible, 'Commessa Beta');
        $thirdProject->setEstimatedAmountCents(200_000);

        $this->entityManager->persist((new Payment())
            ->setProject($firstProject)
            ->setRecordedBy($partner)
            ->setPaidOn(new \DateTimeImmutable('today'))
            ->setDescription('Incasso Alfa 1')
            ->setAmountCents(40_000));
        $this->entityManager->persist((new Payment())
            ->setProject($secondProject)
            ->setRecordedBy($partner)
            ->setPaidOn(new \DateTimeImmutable('today'))
            ->setDescription('Incasso Alfa 2')
            ->setAmountCents(10_000));
        $this->entityManager->persist((new Payment())
            ->setProject($thirdProject)
            ->setRecordedBy($partner)
            ->setPaidOn(new \DateTimeImmutable('today'))
            ->setDescription('Incasso Beta eccedente')
            ->setAmountCents(250_000));
        $this->entityManager->flush();

        $this->client->loginUser($partner);
        $this->client->request('GET', '/economia');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Importi dovuti per cliente');
        self::assertSelectorTextContains('tr[data-client-id="'.$firstClient->getId().'"]', '1.500,00');
        self::assertSelectorTextContains('tr[data-client-id="'.$firstClient->getId().'"]', '500,00');
        self::assertSelectorTextContains('tr[data-client-id="'.$firstClient->getId().'"]', '1.000,00');
        self::assertSelectorTextContains('tr[data-client-id="'.$secondClient->getId().'"]', '0,00');

        $this->client->request('GET', '/clienti');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('thead', 'Dovuto');
        self::assertSelectorTextContains('tr[data-client-id="'.$firstClient->getId().'"]', '1.000,00');
        self::assertSelectorTextContains('tr[data-client-id="'.$secondClient->getId().'"]', '0,00');

        $this->client->loginUser($responsible);
        $this->client->request('GET', '/clienti');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('thead', 'Dovuto');
    }

}
