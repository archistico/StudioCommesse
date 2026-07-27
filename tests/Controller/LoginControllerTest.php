<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;

final class LoginControllerTest extends DatabaseWebTestCase
{
    public function testLoginPageIsPublicAndContainsCsrfProtectedForm(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Accedi');
        self::assertSelectorExists('input[name="_csrf_token"]');
        self::assertSelectorExists('input[autocomplete="current-password"]');
    }

    public function testUnauthenticatedUserIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/dashboard');

        self::assertResponseRedirects('/login');
    }

    public function testInactiveUserCannotAuthenticate(): void
    {
        $this->createUser('disattivato', active: false);

        $crawler = $this->client->request('GET', '/login');
        $form = $crawler->selectButton('Accedi')->form([
            '_username' => 'disattivato',
            '_password' => 'Password-sicura-123!',
        ]);
        $this->client->submit($form);
        $this->client->followRedirect();

        self::assertRouteSame('app_login');
        self::assertSelectorExists('.alert-danger');
    }
}
