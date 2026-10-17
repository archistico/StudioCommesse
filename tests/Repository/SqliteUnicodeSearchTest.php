<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Query\ProjectSearchCriteria;
use App\Repository\ProjectRepository;
use App\Tests\DatabaseWebTestCase;

final class SqliteUnicodeSearchTest extends DatabaseWebTestCase
{
    public function testUnicodeLowercaseAndNullArePreservedAfterReconnecting(): void
    {
        $connection = $this->entityManager->getConnection();
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            self::assertSame('città noè éñç', $connection->fetchOne('SELECT LOWER(:value)', ['value' => 'CITTÀ NOÈ ÉÑÇ']));
            self::assertNull($connection->fetchOne('SELECT LOWER(NULL)'));
            $connection->close();
        }
    }

    public function testProjectSearchAlsoFindsAnAccentedClientName(): void
    {
        $user = $this->createUser('ricerca-commesse-cliente');
        $project = $this->createProject($this->createCustomer('CITTÀ SERVIZI'), $user, 'Commessa per cliente con accento');
        $repository = self::getContainer()->get(ProjectRepository::class);
        self::assertInstanceOf(ProjectRepository::class, $repository);

        $found = $repository->findFiltered(new ProjectSearchCriteria(query: 'città'));

        self::assertCount(1, $found);
        self::assertSame($project->getId(), $found[0]->getId());
    }
}
