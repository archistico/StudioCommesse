<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;

final class AuthorizationTest extends DatabaseWebTestCase
{
    public function testCollaboratorCannotAccessUserAdministration(): void
    {
        $collaborator = $this->createUser('collaboratore');
        $this->client->loginUser($collaborator);

        $this->client->request('GET', '/admin/utenti');

        self::assertResponseStatusCodeSame(403);
    }

    public function testPartnerCanAccessUserAdministration(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $this->client->loginUser($partner);

        $this->client->request('GET', '/admin/utenti');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2.page-title', 'Utenti');
    }

    public function testPartnerInheritsCollaboratorDashboardAccess(): void
    {
        $partner = $this->createUser('socio', UserRole::Partner);
        $this->client->loginUser($partner);

        $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2.page-title', 'Dashboard');
    }
}
