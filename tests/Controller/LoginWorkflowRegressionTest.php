<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\DatabaseWebTestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DomCrawler\Crawler;

final class LoginWorkflowRegressionTest extends DatabaseWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $cache = self::getContainer()->get('cache.rate_limiter');
        self::assertInstanceOf(CacheItemPoolInterface::class, $cache);
        $cache->clear();
    }

    public function testLoginUsesTheSameUsernameNormalizationAsUserCreation(): void
    {
        $this->createUser('mario.rossi');

        $this->submitLogin('  Mario.Rossi  ', 'Password-sicura-123!');

        self::assertResponseRedirects('/dashboard');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testSuccessfulLoginResetsTheLocalFailureCounter(): void
    {
        $this->createUser('reset-tentativi');
        for ($attempt = 0; $attempt < 4; ++$attempt) {
            $this->submitLogin('reset-tentativi', 'Password-errata');
            self::assertResponseRedirects('/login');
        }
        $this->submitLogin('reset-tentativi', 'Password-sicura-123!');
        self::assertResponseRedirects('/dashboard');
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Esci')->form());
        self::assertResponseRedirects('/login');

        $this->submitLogin('reset-tentativi', 'Password-errata');
        self::assertResponseRedirects('/login');
        $this->submitLogin('reset-tentativi', 'Password-sicura-123!');
        self::assertResponseRedirects('/dashboard');
    }

    public function testSuccessfulLoginDoesNotResetTheGlobalIpCounter(): void
    {
        $this->createUser('contatore-globale');
        for ($attempt = 0; $attempt < 25; ++$attempt) {
            if (12 === $attempt) {
                $this->submitLogin('contatore-globale', 'Password-sicura-123!');
                self::assertResponseRedirects('/dashboard');
                $crawler = $this->client->followRedirect();
                $this->client->submit($crawler->selectButton('Esci')->form());
                self::assertResponseRedirects('/login');
            }
            $this->submitLogin('inesistente-'.$attempt, 'Password-errata');
            self::assertResponseRedirects('/login');
        }

        $this->submitLogin('contatore-globale', 'Password-sicura-123!');
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'accesso temporaneamente non disponibile');
    }

    public function testInvalidLogoutCsrfReturnsForbiddenAndPreservesTheSession(): void
    {
        $user = $this->createUser('logout-csrf');
        $this->client->loginUser($user);
        $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();

        $this->client->request('POST', '/logout', ['_csrf_token' => 'invalid']);

        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/dashboard');
        self::assertResponseIsSuccessful();
    }

    public function testInvalidLoginCsrfNeverAuthenticatesTheUser(): void
    {
        $this->createUser('login-csrf');
        $crawler = $this->client->request('GET', '/login');
        $values = $crawler->selectButton('Accedi')->form([
            '_username' => 'login-csrf',
            '_password' => 'Password-sicura-123!',
        ])->getPhpValues();
        $values['_csrf_token'] = 'invalid';

        $this->client->request('POST', '/login', $values);
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/dashboard');
        self::assertResponseRedirects('/login');
    }

    public function testLoginStillUpgradesAndPersistsAnOutdatedPasswordHash(): void
    {
        $user = $this->createUser('login-aggiorna-hash');
        $oldHash = password_hash('Password-sicura-123!', PASSWORD_BCRYPT, ['cost' => 5]);
        $user->setPassword($oldHash);
        $this->entityManager->flush();
        $userId = $user->getId();

        $this->submitLogin('login-aggiorna-hash', 'Password-sicura-123!');
        self::assertResponseRedirects('/dashboard');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->entityManager->clear();
        $saved = $this->entityManager->find(\App\Entity\User::class, $userId);
        self::assertInstanceOf(\App\Entity\User::class, $saved);
        self::assertNotSame($oldHash, $saved->getPassword());
    }

    public function testLoginPreservesTheOriginallyRequestedProtectedPage(): void
    {
        $this->createUser('login-pagina-richiesta');
        $this->client->request('GET', '/commesse');
        self::assertResponseRedirects('/login');

        $this->submitLogin('login-pagina-richiesta', 'Password-sicura-123!');

        self::assertResponseRedirects('/commesse');
    }

    private function submitLogin(string $username, string $password): Crawler
    {
        $crawler = $this->client->request('GET', '/login', [], [], ['REMOTE_ADDR' => '192.0.2.90']);

        return $this->client->submit($crawler->selectButton('Accedi')->form([
            '_username' => $username,
            '_password' => $password,
        ]), [], ['REMOTE_ADDR' => '192.0.2.90']);
    }
}
