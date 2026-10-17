<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('oversizedPasswords')]
    public function testOversizedPasswordsAreRejectedWithoutCreatingAnAccount(string $password): void
    {
        $application = new Application(self::$kernel);
        $tester = new CommandTester($application->find('app:user:create'));
        $tester->setInputs([$password]);

        $status = $tester->execute([
            'username' => 'password-lunga-cli',
            'display-name' => 'Utente CLI',
            '--role' => 'collaborator',
        ]);

        self::assertSame(Command::INVALID, $status);
        self::assertStringContainsString('4096', $tester->getDisplay());
        self::assertSame(0, $this->entityManager->getRepository(User::class)->count([]));
    }

    /** @return iterable<string, array{string}> */
    public static function oversizedPasswords(): iterable
    {
        yield 'ASCII' => [str_repeat('a', 4_097)];
        yield 'multibyte' => [str_repeat('é', 2_049)];
    }

    protected function tearDown(): void
    {
        $this->entityManager->close();
        unset($this->entityManager);
        parent::tearDown();
    }
}
