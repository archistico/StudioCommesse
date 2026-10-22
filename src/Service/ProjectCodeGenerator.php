<?php

declare(strict_types=1);

namespace App\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class ProjectCodeGenerator
{
    public function __construct(private Connection $connection)
    {
    }

    public function nextCode(?DateTimeImmutable $date = null): string
    {
        $year = (int) ($date ?? new DateTimeImmutable())->format('Y');
        $number = $this->connection->transactional(static function (Connection $connection) use ($year): int|string {
            // SQLite precedenti alla 3.35 non supportano RETURNING. La transazione
            // mantiene il lock di scrittura fino alla lettura del progressivo.
            $connection->executeStatement(
                <<<'SQL'
                    INSERT INTO project_code_sequence (year_value, last_number)
                    VALUES (:year, 1)
                    ON CONFLICT (year_value)
                    DO UPDATE SET last_number = project_code_sequence.last_number + 1
                    SQL,
                ['year' => $year],
            );
            $number = $connection->fetchOne(
                'SELECT last_number FROM project_code_sequence WHERE year_value = :year',
                ['year' => $year],
            );

            if (!is_int($number) && !is_string($number)) {
                throw new \RuntimeException('Impossibile generare il progressivo della commessa.');
            }

            return $number;
        });

        return sprintf('%04d-%03d', $year, (int) $number);
    }
}
