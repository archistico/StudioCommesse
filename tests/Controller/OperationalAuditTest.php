<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use App\Enum\UserRole;
use App\Tests\DatabaseWebTestCase;

final class OperationalAuditTest extends DatabaseWebTestCase
{
    public function testPartnerCanFilterOperationalAuditByActionActorAndRequestId(): void
    {
        $partner = $this->createUser('socio-audit', UserRole::Partner);
        $matching = new AuditLog(
            AuditAction::ProjectCreated,
            'maria',
            'App\\Entity\\Project',
            42,
            [
                'name' => 'Commessa correlata',
                'request_id' => 'REQ-audit-1234',
                'route' => 'app_project_new',
                'method' => 'POST',
            ],
            '127.0.0.1',
        );
        $other = new AuditLog(
            AuditAction::LoginFailed,
            'luca',
            null,
            null,
            ['request_id' => 'REQ-audit-9999', 'reason' => 'Credenziali non valide'],
            '127.0.0.2',
        );
        $this->entityManager->persist($matching);
        $this->entityManager->persist($other);
        $this->entityManager->flush();
        $this->client->loginUser($partner);

        $this->client->request('GET', '/audit?action=project.created&actor=maria&request_id=REQ-audit-1234');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2.page-title', 'Audit operativo');
        self::assertSelectorTextContains('table', 'Commessa creata');
        self::assertSelectorTextContains('table', 'Commessa correlata');
        self::assertSelectorTextContains('table', 'REQ-audit-1234');
        $resultRows = $this->client->getCrawler()->filter('table tbody')->text();
        self::assertStringNotContainsString('Accesso non riuscito', $resultRows);
        self::assertSelectorTextContains('body', 'Eventi trovati');
        self::assertSelectorTextContains('body', '1');
    }

    public function testCollaboratorCannotOpenAuditOrExport(): void
    {
        $collaborator = $this->createUser('collaboratore-audit');
        $this->client->loginUser($collaborator);

        $this->client->request('GET', '/audit');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/audit/csv');
        self::assertResponseStatusCodeSame(403);
    }

    public function testPartnerCanExportFilteredAuditAsUtf8Csv(): void
    {
        $partner = $this->createUser('socio-audit-csv', UserRole::Partner);
        $this->entityManager->persist(new AuditLog(
            AuditAction::ActivityUpdated,
            'anna',
            'App\\Entity\\Activity',
            9,
            ['title' => 'Verifica CSV', 'request_id' => 'REQ-csv-12345678'],
        ));
        $this->entityManager->persist(new AuditLog(AuditAction::ClientCreated, 'bruno', 'App\\Entity\\Client', 3));
        $this->entityManager->flush();
        $this->client->loginUser($partner);

        $this->client->request('GET', '/audit/csv?action=activity.updated');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'text/csv; charset=UTF-8');
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString('Attività aggiornata', $content);
        self::assertStringContainsString('Verifica CSV', $content);
        self::assertStringNotContainsString('Cliente creato', $content);
    }
}
