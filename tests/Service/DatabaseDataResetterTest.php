<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\DatabaseDataResetter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

final class DatabaseDataResetterTest extends TestCase
{
    private Connection $connection;

    /** @var list<string> */
    private const TABLES_TO_CLEAR = [
        'attachment',
        'time_entry',
        'expense',
        'payment',
        'activity',
        'project',
        'client',
        'project_code_sequence',
        'audit_log',
    ];

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);
        $this->connection->executeStatement(
            'CREATE TABLE app_user (id INTEGER PRIMARY KEY AUTOINCREMENT, username VARCHAR(180) NOT NULL)',
        );
        $this->connection->executeStatement(
            'CREATE TABLE doctrine_migration_versions (version VARCHAR(191) PRIMARY KEY NOT NULL)',
        );
        foreach (self::TABLES_TO_CLEAR as $table) {
            $this->connection->executeStatement(
                sprintf('CREATE TABLE %s (id INTEGER PRIMARY KEY AUTOINCREMENT, value VARCHAR(50))', $table),
            );
        }
    }

    public function testItDeletesApplicationDataAndKeepsUsersAndMigrations(): void
    {
        $this->connection->executeStatement("INSERT INTO app_user (username) VALUES ('socio'), ('collaboratore')");
        $this->connection->executeStatement(
            "INSERT INTO doctrine_migration_versions (version) VALUES ('DoctrineMigrations\\\\Version1')",
        );
        foreach (self::TABLES_TO_CLEAR as $table) {
            $this->connection->executeStatement(sprintf("INSERT INTO %s (value) VALUES ('dato')", $table));
        }

        $summary = (new DatabaseDataResetter($this->connection))->resetKeepingUsers();

        self::assertSame(2, $summary['users']);
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_user'));
        self::assertSame(
            1,
            (int) $this->connection->fetchOne('SELECT COUNT(*) FROM doctrine_migration_versions'),
        );
        foreach (self::TABLES_TO_CLEAR as $table) {
            self::assertSame(1, $summary['deleted'][$table]);
            self::assertSame(0, (int) $this->connection->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table)));
        }

        $this->connection->executeStatement("INSERT INTO client (value) VALUES ('nuovo')");
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT id FROM client'));
        $this->connection->executeStatement("INSERT INTO app_user (username) VALUES ('nuovo')");
        self::assertSame(3, (int) $this->connection->fetchOne("SELECT id FROM app_user WHERE username = 'nuovo'"));
    }

    public function testItRefusesAnUnknownSchemaBeforeDeletingAnything(): void
    {
        $this->connection->executeStatement("INSERT INTO app_user (username) VALUES ('socio')");
        $this->connection->executeStatement("INSERT INTO client (value) VALUES ('cliente')");
        $this->connection->executeStatement('CREATE TABLE future_data (id INTEGER PRIMARY KEY)');

        try {
            (new DatabaseDataResetter($this->connection))->resetKeepingUsers();
            self::fail('Uno schema sconosciuto deve interrompere la pulizia.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('future_data', $exception->getMessage());
        }

        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_user'));
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM client'));
    }

    protected function tearDown(): void
    {
        $this->connection->close();
    }
}
