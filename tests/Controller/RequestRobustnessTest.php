<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;

final class RequestRobustnessTest extends DatabaseWebTestCase
{
    public function testEveryResponseContainsARequestIdentifier(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->has('X-Request-ID'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{24}$/', (string) $this->client->getResponse()->headers->get('X-Request-ID'));
    }

    public function testValidIncomingRequestIdentifierIsPreserved(): void
    {
        $this->client->setServerParameter('HTTP_X_REQUEST_ID', 'support-case-12345');
        $this->client->request('GET', '/login');

        self::assertResponseHeaderSame('X-Request-ID', 'support-case-12345');
    }

    public function testMethodNotAllowedUsesTheSafeErrorPage(): void
    {
        $user = $this->createUser('robustezza', UserRole::Collaborator);
        $this->client->loginUser($user);
        $this->client->request('POST', '/commesse');

        self::assertResponseStatusCodeSame(405);
        self::assertSelectorTextContains('body', 'Operazione non consentita');
        self::assertStringNotContainsString('Stack trace', (string) $this->client->getResponse()->getContent());
    }
}
