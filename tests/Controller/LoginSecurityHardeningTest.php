<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\Tests\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;

final class LoginSecurityHardeningTest extends DatabaseWebTestCase
{
    public function testFiveFailuresTemporarilyBlockAValidPasswordAndAreAudited(): void
    {
        $username = 'utente-blocco-login';
        $password = 'Password-sicura-123!';
        $this->createUser($username, password: $password);

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $this->submitLogin($username, 'Password-errata-'.$attempt);
            self::assertRouteSame('app_login');
            self::assertSelectorExists('.alert-danger');
        }

        $this->submitLogin($username, $password);

        self::assertRouteSame('app_login');
        self::assertSelectorTextContains('.alert-danger', 'accesso temporaneamente non disponibile');

        $logs = $this->auditLogs();
        self::assertCount(5, array_filter($logs, static fn (AuditLog $entry): bool => AuditAction::LoginFailed === $entry->getAction()));
        $blocked = array_values(array_filter($logs, static fn (AuditLog $entry): bool => AuditAction::LoginThrottled === $entry->getAction()));
        self::assertCount(1, $blocked);
        self::assertSame(60, $blocked[0]->getDetails()['lockout_minutes'] ?? null);
        self::assertSame('temporarily_throttled', $blocked[0]->getDetails()['failure_category'] ?? null);
    }

    public function testFailedLoginAuditDoesNotStoreTheRawIdentifierOrAuthenticationReason(): void
    {
        $identifier = 'Persona.Riservata';
        $this->submitLogin($identifier, 'Password-errata');

        self::assertRouteSame('app_login');
        self::assertStringNotContainsString($identifier, (string) $this->client->getResponse()->getContent());

        $failed = array_values(array_filter(
            $this->auditLogs(),
            static fn (AuditLog $entry): bool => AuditAction::LoginFailed === $entry->getAction(),
        ));
        self::assertCount(1, $failed);
        self::assertNull($failed[0]->getActorIdentifier());
        self::assertSame('Identificativo protetto', $failed[0]->getActorLabel());
        self::assertSame('credentials_rejected', $failed[0]->getDetails()['failure_category'] ?? null);
        self::assertMatchesRegularExpression('/^[a-f0-9]{24}$/', (string) ($failed[0]->getDetails()['identifier_fingerprint'] ?? ''));
        self::assertArrayNotHasKey('reason', $failed[0]->getDetails());
        self::assertStringNotContainsString(mb_strtolower($identifier), json_encode($failed[0]->getDetails(), JSON_THROW_ON_ERROR));
    }

    public function testDynamicResponsesReceivePrivacyAndBrowserSecurityHeaders(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('x-content-type-options', 'nosniff');
        self::assertResponseHeaderSame('x-frame-options', 'DENY');
        self::assertResponseHeaderSame('referrer-policy', 'no-referrer');
        self::assertResponseHeaderSame('permissions-policy', 'camera=(), microphone=(), geolocation=()');
        self::assertResponseHeaderSame('cross-origin-opener-policy', 'same-origin');
        self::assertStringContainsString("frame-ancestors 'none'", (string) $this->client->getResponse()->headers->get('Content-Security-Policy'));
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertFalse($this->client->getResponse()->headers->has('Strict-Transport-Security'));

        $this->client->request('GET', 'https://localhost/login');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('strict-transport-security', 'max-age=31536000; includeSubDomains');
    }

    private function submitLogin(string $username, string $password): void
    {
        $crawler = $this->client->request('GET', '/login', [], [], ['REMOTE_ADDR' => '192.0.2.55']);
        $form = $crawler->selectButton('Accedi')->form([
            '_username' => $username,
            '_password' => $password,
        ]);
        $this->client->submit($form, [], ['REMOTE_ADDR' => '192.0.2.55']);
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
    }

    /** @return list<AuditLog> */
    private function auditLogs(): array
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entries = $entityManager->getRepository(AuditLog::class)->findBy([], ['id' => 'ASC']);

        /** @var list<AuditLog> $entries */
        return $entries;
    }
}
