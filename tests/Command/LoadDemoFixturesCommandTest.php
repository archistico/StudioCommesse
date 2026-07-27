<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Activity;
use App\Entity\TimeEntry;
use App\Repository\ActivityRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class LoadDemoFixturesCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testStandardProfileIsDeterministicMultiContributorAndPreservesManualEntries(): void
    {
        $firstRun = $this->executeFixtures();
        self::assertStringContainsString('30 commesse', $firstRun->getDisplay());
        self::assertStringContainsString('200 attività, 600 registrazioni ore', $firstRun->getDisplay());
        $this->assertStandardProfileCounts();
        $this->assertEntryDistribution();
        $this->assertMultiContributorCoverage();
        $this->assertNoUserOverlaps();

        $secondRun = $this->executeFixtures();
        self::assertStringContainsString('Fixtures caricate o riconciliate correttamente.', $secondRun->getDisplay());
        $this->assertStandardProfileCounts();

        /** @var ActivityRepository $activities */
        $activities = self::getContainer()->get(ActivityRepository::class);
        $activity = $activities->findOneBy(['title' => 'Fase 1 — Rilievo']);
        self::assertInstanceOf(Activity::class, $activity);
        $assignee = $activity->getAssignee();
        self::assertNotNull($assignee);

        $manualEntry = (new TimeEntry())
            ->setActivity($activity)
            ->setUser($assignee)
            ->setStartedAt(new DateTimeImmutable('2026-12-30 09:00'))
            ->setEndedAt(new DateTimeImmutable('2026-12-30 10:00'))
            ->setDescription('Registrazione manuale da conservare.');
        $legacyEntry = (new TimeEntry())
            ->setActivity($activity)
            ->setUser($assignee)
            ->setStartedAt(new DateTimeImmutable('2026-12-31 09:00'))
            ->setEndedAt(new DateTimeImmutable('2026-12-31 10:00'))
            ->setDescription('Rilievo — sessione 99.');
        $this->entityManager->persist($manualEntry);
        $this->entityManager->persist($legacyEntry);
        $this->entityManager->flush();

        $thirdRun = $this->executeFixtures();
        self::assertStringContainsString('Fixtures caricate o riconciliate correttamente.', $thirdRun->getDisplay());

        $connection = $this->entityManager->getConnection();
        self::assertSame(601, (int) $connection->fetchOne('SELECT COUNT(*) FROM time_entry'));
        self::assertSame(1, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM time_entry WHERE description = :description',
            ['description' => 'Registrazione manuale da conservare.'],
        ));
        self::assertSame(0, (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM time_entry WHERE description = :description',
            ['description' => 'Rilievo — sessione 99.'],
        ));
    }

    private function executeFixtures(): CommandTester
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:fixtures:load'));
        $status = $tester->execute([]);
        self::assertSame(Command::SUCCESS, $status, $tester->getDisplay());

        return $tester;
    }

    private function assertStandardProfileCounts(): void
    {
        $connection = $this->entityManager->getConnection();
        self::assertSame(8, (int) $connection->fetchOne('SELECT COUNT(*) FROM app_user'));
        self::assertSame(10, (int) $connection->fetchOne('SELECT COUNT(*) FROM client'));
        self::assertSame(30, (int) $connection->fetchOne('SELECT COUNT(*) FROM project'));
        self::assertSame(200, (int) $connection->fetchOne('SELECT COUNT(*) FROM activity'));
        self::assertSame(600, (int) $connection->fetchOne('SELECT COUNT(*) FROM time_entry'));
        self::assertSame(240, (int) $connection->fetchOne('SELECT COUNT(*) FROM expense'));
        self::assertSame(120, (int) $connection->fetchOne('SELECT COUNT(*) FROM payment'));
    }

    private function assertEntryDistribution(): void
    {
        $rows = $this->entityManager->getConnection()->fetchAllAssociative(<<<'SQL'
SELECT entry_count, COUNT(*) AS activity_count
FROM (
    SELECT activity.id, COUNT(time_entry.id) AS entry_count
    FROM activity
    LEFT JOIN time_entry ON time_entry.activity_id = activity.id
    GROUP BY activity.id
)
GROUP BY entry_count
ORDER BY entry_count
SQL);

        $distribution = [];
        foreach ($rows as $row) {
            $distribution[(int) $row['entry_count']] = (int) $row['activity_count'];
        }

        self::assertSame([0 => 20, 1 => 40, 2 => 60, 4 => 50, 8 => 30], $distribution);
    }

    private function assertMultiContributorCoverage(): void
    {
        $connection = $this->entityManager->getConnection();
        self::assertGreaterThan(0, (int) $connection->fetchOne(<<<'SQL'
SELECT COUNT(*)
FROM activity
INNER JOIN app_user ON app_user.id = activity.assignee_id
WHERE app_user.role = 'ROLE_PARTNER'
SQL));
        self::assertGreaterThan(0, (int) $connection->fetchOne(<<<'SQL'
SELECT COUNT(*)
FROM activity
INNER JOIN app_user ON app_user.id = activity.assignee_id
WHERE app_user.role = 'ROLE_COLLABORATOR'
SQL));
        self::assertGreaterThan(0, (int) $connection->fetchOne(<<<'SQL'
SELECT COUNT(*)
FROM time_entry
INNER JOIN activity ON activity.id = time_entry.activity_id
WHERE time_entry.user_id <> activity.assignee_id
SQL));
        self::assertGreaterThan(0, (int) $connection->fetchOne(<<<'SQL'
SELECT COUNT(*)
FROM time_entry
INNER JOIN app_user ON app_user.id = time_entry.user_id
WHERE app_user.role = 'ROLE_PARTNER'
SQL));
        self::assertSame(5, (int) $connection->fetchOne(<<<'SQL'
SELECT MAX(contributor_count)
FROM (
    SELECT activity_id, COUNT(DISTINCT user_id) AS contributor_count
    FROM time_entry
    GROUP BY activity_id
)
SQL));
    }

    private function assertNoUserOverlaps(): void
    {
        $overlaps = (int) $this->entityManager->getConnection()->fetchOne(<<<'SQL'
SELECT COUNT(*)
FROM time_entry first_entry
INNER JOIN time_entry second_entry
    ON first_entry.id < second_entry.id
   AND first_entry.user_id = second_entry.user_id
   AND first_entry.started_at < second_entry.ended_at
   AND second_entry.started_at < first_entry.ended_at
SQL);

        self::assertSame(0, $overlaps);
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        unset($this->entityManager);
        parent::tearDown();
    }
}
