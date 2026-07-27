<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateUserCommandTest extends KernelTestCase
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

    public function testGuidedBootstrapCreatesFirstPartnerAndSkipsLaterRuns(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:user:create');

        $firstRun = new CommandTester($command);
        $firstRun->setInputs([
            'mario.rossi',
            'Mario Rossi',
            'Password-sicura-123!',
        ]);

        $status = $firstRun->execute([
            '--role' => 'partner',
            '--skip-if-active-partner-exists' => true,
        ]);

        self::assertSame(Command::SUCCESS, $status);

        /** @var UserRepository $repository */
        $repository = self::getContainer()->get(UserRepository::class);
        $user = $repository->findOneBy(['username' => 'mario.rossi']);

        self::assertInstanceOf(User::class, $user);
        self::assertSame(UserRole::Partner, $user->getRole());
        self::assertTrue($user->isActive());

        $secondRun = new CommandTester($command);
        $secondStatus = $secondRun->execute([
            '--role' => 'partner',
            '--skip-if-active-partner-exists' => true,
        ]);

        self::assertSame(Command::SUCCESS, $secondStatus);
        self::assertStringContainsString('già presente almeno un socio attivo', $secondRun->getDisplay());
        self::assertSame(1, $repository->countActiveByRole(UserRole::Partner));
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        unset($this->entityManager);
        parent::tearDown();
    }
}
