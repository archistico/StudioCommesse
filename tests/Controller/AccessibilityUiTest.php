<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;

final class AccessibilityUiTest extends DatabaseWebTestCase
{
    public function testLoginProvidesKeyboardLandmarkAndSecurityHelp(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.skip-link[href="#main-content"]');
        self::assertSelectorExists('main#main-content[tabindex="-1"]');
        self::assertSelectorExists('form[aria-describedby="login-security-note"]');
        self::assertSelectorExists('input[autocomplete="username"][autocapitalize="none"]');
        self::assertSelectorExists('input[autocomplete="current-password"]');
        self::assertSelectorTextContains('#login-security-note', '5 tentativi');
        self::assertSelectorTextContains('#login-security-note', 'un’ora');
    }

    public function testAuthenticatedLayoutHasLandmarksCurrentPageAndScopedTableHeaders(): void
    {
        $user = $this->createUser('accessibilita');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/dashboard');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.skip-link[href="#main-content"]');
        self::assertSelectorExists('aside[aria-label="Navigazione principale"]');
        self::assertSelectorExists('main#main-content[tabindex="-1"]');
        self::assertSelectorExists('a.nav-link[href="/dashboard"][aria-current="page"]');
        self::assertGreaterThan(0, $crawler->filter('table thead th')->count());
        self::assertSame($crawler->filter('table thead th')->count(), $crawler->filter('table thead th[scope="col"]')->count());
    }
}
