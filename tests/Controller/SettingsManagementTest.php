<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Service\RequestRuntimeLock;
use App\Tests\DatabaseWebTestCase;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class SettingsManagementTest extends DatabaseWebTestCase
{
    public function testSettingsReplaceUsersInPartnerNavigation(): void
    {
        $this->client->loginUser($this->createUser('socio-impostazioni', UserRole::Partner));
        $crawler = $this->client->request('GET', '/admin/impostazioni');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside a[href="/admin/impostazioni"].active');
        self::assertSelectorNotExists('aside a[href="/admin/utenti"]');
        $this->client->click($crawler->selectLink('Gestisci utenti')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('aside a[href="/admin/impostazioni"].active');
    }

    #[DataProvider('restrictedRoutes')]
    public function testCollaboratorsCannotAccessSettings(string $method, string $path): void
    {
        $this->client->loginUser($this->createUser('collaboratore-impostazioni'));
        $this->client->request($method, $path);
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorNotExists('aside a[href="/admin/impostazioni"]');
    }

    /** @return iterable<string, array{string, string}> */
    public static function restrictedRoutes(): iterable
    {
        yield 'pagina' => ['GET', '/admin/impostazioni'];
        yield 'backup' => ['POST', '/admin/impostazioni/backup'];
        yield 'azzeramento' => ['POST', '/admin/impostazioni/azzera-dati'];
    }

    public function testBackupDownloadsAnIndependentConsistentDatabaseAndDeletesTemporaryFile(): void
    {
        $partner = $this->createUser('socio-backup', UserRole::Partner);
        $customer = $this->createCustomer('Cliente nel backup');
        $this->createProject($customer, $partner);
        $this->client->loginUser($partner);
        $crawler = $this->client->request('GET', '/admin/impostazioni');
        $this->client->submit($crawler->selectButton('Scarica database')->form());
        self::assertResponseIsSuccessful();
        $response = $this->client->getResponse();
        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame('application/vnd.sqlite3', $response->headers->get('Content-Type'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $downloadPath = $response->getFile()->getPathname();
        self::assertFileDoesNotExist($downloadPath);
        $path = $downloadPath.'.inspection.sqlite';
        file_put_contents($path, $this->client->getInternalResponse()->getContent());
        try {
            $snapshot = new PDO('sqlite:'.$path);
            self::assertSame('ok', $snapshot->query('PRAGMA integrity_check')->fetchColumn());
            self::assertFalse($snapshot->query('PRAGMA foreign_key_check')->fetch());
            self::assertSame(1, (int) $snapshot->query('SELECT COUNT(*) FROM app_user')->fetchColumn());
            self::assertSame('Cliente nel backup', $snapshot->query('SELECT name FROM client')->fetchColumn());
            $snapshot = null;
            $this->createCustomer('Creato dopo il backup');
            $snapshot = new PDO('sqlite:'.$path);
            self::assertSame(1, (int) $snapshot->query('SELECT COUNT(*) FROM client')->fetchColumn());
            $snapshot = null;
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testResetDeletesApplicationDataButPreservesEntireUserRowsAndMigrations(): void
    {
        $partner = $this->createUser('socio-reset', UserRole::Partner);
        $collaborator = $this->createUser('collaboratore-reset', active: false);
        $collaborator->setDefaultHourlyRateCents(4200);
        $this->entityManager->flush();
        $project = $this->createProject($this->createCustomer(), $partner);
        $activity = $this->createTestActivity($project, $partner);
        $this->createTestTimeEntry($activity, $partner, '2026-10-01 09:00', null);
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS doctrine_migration_versions (version VARCHAR(191) PRIMARY KEY)');
        $connection->executeStatement("INSERT OR IGNORE INTO doctrine_migration_versions (version) VALUES ('test-reset')");
        $before = $connection->fetchAllAssociative('SELECT * FROM app_user ORDER BY id');
        $this->client->loginUser($partner);
        $crawler = $this->client->request('GET', '/admin/impostazioni');
        $this->client->submit($crawler->selectButton('Svuota database')->form(['confirmation' => 'CANCELLA DATI']));
        self::assertResponseRedirects('/admin/impostazioni');
        self::assertSame($before, $connection->fetchAllAssociative('SELECT * FROM app_user ORDER BY id'));
        foreach (['attachment', 'time_entry', 'expense', 'payment', 'activity', 'project', 'client', 'project_code_sequence', 'audit_log'] as $table) {
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$table), $table);
        }
        self::assertSame(1, (int) $connection->fetchOne("SELECT COUNT(*) FROM doctrine_migration_versions WHERE version = 'test-reset'"));
        $this->entityManager->clear();
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.alert-success', '2 utenti conservati');
        self::assertSame(2, $this->entityManager->getRepository(User::class)->count([]));
    }

    public function testResetRequiresCsrfAndExactConfirmation(): void
    {
        $this->client->loginUser($this->createUser('socio-conferma', UserRole::Partner));
        $this->createCustomer();
        $this->client->request('POST', '/admin/impostazioni/azzera-dati', ['confirmation' => 'CANCELLA DATI']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/impostazioni/backup');
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/admin/impostazioni');
        $this->client->submit($crawler->selectButton('Svuota database')->form(['confirmation' => 'cancella']));
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM client'));
    }

    public function testResetRefusesConcurrentRequestsWithoutDeletingData(): void
    {
        $this->client->loginUser($this->createUser('socio-lock', UserRole::Partner));
        $this->createCustomer();
        $crawler = $this->client->request('GET', '/admin/impostazioni');
        $otherRequest = self::getContainer()->get(RequestRuntimeLock::class)->acquireShared();
        try {
            $this->client->submit($crawler->selectButton('Svuota database')->form(['confirmation' => 'CANCELLA DATI']));
            self::assertResponseStatusCodeSame(503);
            self::assertSelectorTextContains('.alert-danger', 'altre operazioni');
            self::assertSame(1, (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM client'));
        } finally {
            $otherRequest->release();
        }
    }
}
