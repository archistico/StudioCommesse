<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Client;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testOptionalValuesAreNormalized(): void
    {
        $client = (new Client())
            ->setName('  Studio Rossi  ')
            ->setEmail('  INFO@EXAMPLE.IT ')
            ->setTaxCode(' abc123 ')
            ->setPhone('   ');

        self::assertSame('Studio Rossi', $client->getName());
        self::assertSame('info@example.it', $client->getEmail());
        self::assertSame('ABC123', $client->getTaxCode());
        self::assertNull($client->getPhone());
    }

    public function testClientCanBeArchivedAndRestored(): void
    {
        $client = new Client();
        $archivedAt = new DateTimeImmutable('2026-07-27 12:00:00');

        $client->archive($archivedAt);
        self::assertTrue($client->isArchived());
        self::assertSame($archivedAt, $client->getArchivedAt());

        $client->restore();
        self::assertFalse($client->isArchived());
        self::assertNull($client->getArchivedAt());
    }
}
