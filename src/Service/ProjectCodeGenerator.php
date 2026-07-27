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
        $number = $this->connection->executeQuery(
            <<<'SQL'
                INSERT INTO project_code_sequence (year_value, last_number)
                VALUES (:year, 1)
                ON CONFLICT (year_value)
                DO UPDATE SET last_number = project_code_sequence.last_number + 1
                RETURNING last_number
                SQL,
            ['year' => $year],
        )->fetchOne();

        if (!is_int($number) && !is_string($number)) {
            throw new \RuntimeException('Impossibile generare il progressivo della commessa.');
        }

        return sprintf('%04d-%03d', $year, (int) $number);
    }
}
