<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;

final readonly class DatabaseDataResetter
{
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

    /** @var list<string> */
    private const TABLES_TO_PRESERVE = [
        'app_user',
        'doctrine_migration_versions',
    ];

    public function __construct(private Connection $connection)
    {
    }

    /**
     * @return array{users: int, deleted: array<string, int>}
     */
    public function resetKeepingUsers(): array
    {
        $this->assertExpectedSchema();
        $platform = $this->connection->getDatabasePlatform();

        return $this->connection->transactional(function (Connection $connection) use ($platform): array {
            $deleted = [];
            foreach (self::TABLES_TO_CLEAR as $table) {
                $quotedTable = $platform->quoteSingleIdentifier($table);
                $deleted[$table] = (int) $connection->executeStatement('DELETE FROM '.$quotedTable);
            }

            if ($platform instanceof SQLitePlatform) {
                foreach (self::TABLES_TO_CLEAR as $table) {
                    $connection->executeStatement('DELETE FROM sqlite_sequence WHERE name = ?', [$table]);
                }
            }

            return [
                'users' => (int) $connection->fetchOne(
                    'SELECT COUNT(*) FROM '.$platform->quoteSingleIdentifier('app_user'),
                ),
                'deleted' => $deleted,
            ];
        });
    }

    private function assertExpectedSchema(): void
    {
        $actualTables = array_map(
            static fn (string $table): string => strtolower($table),
            $this->connection->createSchemaManager()->listTableNames(),
        );
        $expectedTables = [...self::TABLES_TO_CLEAR, ...self::TABLES_TO_PRESERVE];

        $missingTables = array_values(array_diff($expectedTables, $actualTables));
        if ([] !== $missingTables) {
            throw new \RuntimeException(
                'Schema incompleto; tabelle mancanti: '.implode(', ', $missingTables).'. Eseguire prima le migrazioni.',
            );
        }

        $unexpectedTables = array_values(array_diff($actualTables, $expectedTables));
        if ([] !== $unexpectedTables) {
            throw new \RuntimeException(
                'Schema non riconosciuto; nessun dato è stato cancellato. Tabelle inattese: '.implode(', ', $unexpectedTables).'.',
            );
        }
    }
}
