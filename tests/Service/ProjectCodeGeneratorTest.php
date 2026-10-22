<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\ProjectCodeGenerator;
use App\Tests\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;

final class ProjectCodeGeneratorTest extends DatabaseWebTestCase
{
    public function testGeneratorProducesAnnualProgressiveCodes(): void
    {
        $generator = self::getContainer()->get(ProjectCodeGenerator::class);
        self::assertInstanceOf(ProjectCodeGenerator::class, $generator);

        self::assertSame('2030-001', $generator->nextCode(new DateTimeImmutable('2030-01-05')));
        self::assertSame('2030-002', $generator->nextCode(new DateTimeImmutable('2030-12-31')));
        self::assertSame('2031-001', $generator->nextCode(new DateTimeImmutable('2031-01-01')));
    }

    public function testGeneratorContinuesExistingSequenceAndCommitsOutsideATransaction(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement(
            'INSERT INTO project_code_sequence (year_value, last_number) VALUES (2030, 999)',
        );
        $generator = new ProjectCodeGenerator($connection);

        self::assertSame('2030-1000', $generator->nextCode(new DateTimeImmutable('2030-06-01')));
        self::assertFalse($connection->isTransactionActive());

        $otherConnection = DriverManager::getConnection($connection->getParams());
        try {
            self::assertSame(1000, (int) $otherConnection->fetchOne(
                'SELECT last_number FROM project_code_sequence WHERE year_value = 2030',
            ));
            self::assertSame('2030-1001', (new ProjectCodeGenerator($otherConnection))->nextCode(new DateTimeImmutable('2030-06-01')));
        } finally {
            $otherConnection->close();
        }
    }

    #[DataProvider('sequenceStates')]
    public function testOuterRollbackRestoresSequence(?int $lastNumber, string $expectedCode): void
    {
        $connection = $this->entityManager->getConnection();
        if (null !== $lastNumber) {
            $connection->executeStatement(
                'INSERT INTO project_code_sequence (year_value, last_number) VALUES (2030, :number)',
                ['number' => $lastNumber],
            );
        }
        $generator = new ProjectCodeGenerator($connection);
        $date = new DateTimeImmutable('2030-06-01');

        $connection->beginTransaction();
        try {
            self::assertSame($expectedCode, $generator->nextCode($date));
            self::assertTrue($connection->isTransactionActive());
            self::assertSame(1, $connection->getTransactionNestingLevel());
        } finally {
            $connection->rollBack();
        }

        self::assertSame($expectedCode, $generator->nextCode($date));
    }

    /** @return iterable<string, array{?int, string}> */
    public static function sequenceStates(): iterable
    {
        yield 'prima commessa dell’anno' => [null, '2030-001'];
        yield 'progressivo esistente' => [42, '2030-043'];
    }
}
