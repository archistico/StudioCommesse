<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;

final class ResponsibleUserGuardTest extends DatabaseWebTestCase
{
    public function testPartnerCannotDeactivateUserResponsibleForNonArchivedProject(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $responsible = $this->createUser('responsabile');
        $this->createProject($this->createCustomer(), $responsible);
        $this->client->loginUser($partner);
        $responsibleId = $responsible->getId();
        self::assertNotNull($responsibleId);

        $crawler = $this->client->request('GET', '/admin/utenti/'.$responsibleId.'/modifica');
        $form = $crawler->selectButton('Salva modifiche')->form([
            'user[displayName]' => $responsible->getDisplayName(),
            'user[username]' => $responsible->getUsername(),
            'user[role]' => UserRole::Collaborator->value,
            'user[plainPassword]' => '',
            'user[active]' => false,
        ]);
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'responsabile di commesse non archiviate');
        $this->entityManager->clear();
        $responsible = $this->entityManager->find(User::class, $responsibleId);
        self::assertInstanceOf(User::class, $responsible);
        self::assertTrue($responsible->isActive());
    }
}
