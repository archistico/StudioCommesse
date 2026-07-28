<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\Service\AuditPrivacyGuard;
use PHPUnit\Framework\TestCase;

final class AuditPrivacyGuardTest extends TestCase
{
    public function testFingerprintsAreStableWhileMirroredLogsExcludePersonalValues(): void
    {
        $guard = new AuditPrivacyGuard('secret-di-test');
        $first = $guard->loginIdentifierFingerprint('  Mario.Rossi  ');
        $second = $guard->loginIdentifierFingerprint('mario.rossi');

        self::assertSame($first, $second);
        self::assertMatchesRegularExpression('/^[a-f0-9]{24}$/', (string) $first);
        self::assertStringNotContainsString('mario', (string) $first);

        $entry = new AuditLog(
            AuditAction::ProjectUpdated,
            'mario.rossi',
            'App\\Entity\\Project',
            42,
            ['name' => 'Cliente riservato', 'request_id' => 'REQ-privacy-1234', 'route' => 'app_project_edit', 'method' => 'POST'],
            '192.0.2.20',
        );
        $context = $guard->logContext($entry);
        $serialized = json_encode($context, JSON_THROW_ON_ERROR);

        self::assertSame(['name'], $context['detail_keys']);
        self::assertStringNotContainsString('mario.rossi', $serialized);
        self::assertStringNotContainsString('Cliente riservato', $serialized);
        self::assertStringNotContainsString('192.0.2.20', $serialized);
        self::assertArrayNotHasKey('details', $context);
        self::assertArrayNotHasKey('actor', $context);
        self::assertArrayNotHasKey('ip_address', $context);
    }
}
