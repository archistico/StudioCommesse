<?php

declare(strict_types=1);

namespace App\Doctrine;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Pdo\Sqlite;
use SensitiveParameter;

#[AsMiddleware]
final class SqliteUnicodeMiddleware implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return new class ($driver) extends AbstractDriverMiddleware {
            public function connect(#[SensitiveParameter] array $params): Connection
            {
                $connection = parent::connect($params);
                $native = $connection->getNativeConnection();

                if ($native instanceof Sqlite) {
                    // Match the Unicode normalization already used for search parameters.
                    $native->createFunction(
                        'LOWER',
                        static fn (?string $value): ?string => null === $value ? null : mb_strtolower($value, 'UTF-8'),
                        1,
                        Sqlite::DETERMINISTIC,
                    );
                }

                return $connection;
            }
        };
    }
}
