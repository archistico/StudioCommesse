<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;

final class UserManagementTest extends DatabaseWebTestCase
{
    public function testPartnerCreatesCollaboratorThroughClassicSymfonyForm(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/admin/utenti/nuovo');
        $form = $crawler->selectButton('Crea utente')->form([
            'user[displayName]' => 'Anna Verdi',
            'user[username]' => 'anna.verdi',
            'user[role]' => UserRole::Collaborator->value,
            'user[plainPassword]' => 'Password-robusta-456!',
            'user[active]' => '1',
        ]);

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/utenti');

        $created = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'anna.verdi']);
        self::assertInstanceOf(User::class, $created);
        self::assertSame(UserRole::Collaborator, $created->getRole());
        self::assertTrue($created->isActive());

        $audit = $this->entityManager->getRepository(AuditLog::class)->findOneBy([
            'action' => AuditAction::UserCreated,
            'subjectId' => $created->getId(),
        ]);
        self::assertInstanceOf(AuditLog::class, $audit);
    }

    public function testStandardHourlyRateIsRenderedBeforeTheCreateButton(): void
    {
        $partner = $this->createUser('socio-form-layout', UserRole::Partner);
        $this->client->loginUser($partner);

        $this->client->request('GET', '/admin/utenti/nuovo');

        self::assertResponseIsSuccessful();
        $html = (string) $this->client->getResponse()->getContent();
        $ratePosition = strpos($html, 'name="user[defaultHourlyRateCents]"');
        $submitPosition = strpos($html, '>Crea utente</button>');
        self::assertIsInt($ratePosition);
        self::assertIsInt($submitPosition);
        self::assertLessThan($submitPosition, $ratePosition);
        self::assertStringContainsString('btn btn-primary w-100', $html);
    }

    public function testCreateFormRejectsShortPassword(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $this->client->loginUser($partner);

        $crawler = $this->client->request('GET', '/admin/utenti/nuovo');
        $form = $crawler->selectButton('Crea utente')->form([
            'user[displayName]' => 'Anna Verdi',
            'user[username]' => 'anna.verdi',
            'user[role]' => UserRole::Collaborator->value,
            'user[plainPassword]' => 'corta',
            'user[active]' => '1',
        ]);

        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'almeno 12');
    }
}
