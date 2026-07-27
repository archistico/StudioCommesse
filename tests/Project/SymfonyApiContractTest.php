<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Repository\UserRepository;
use App\Security\ActiveUserChecker;
use App\Security\Voter\ProjectVoter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;

final class SymfonyApiContractTest extends TestCase
{
    public function testUserRepositoryUsesCurrentPasswordUpgraderInterface(): void
    {
        self::assertTrue(is_subclass_of(UserRepository::class, PasswordUpgraderInterface::class));

        $method = new ReflectionMethod(UserRepository::class, 'upgradePassword');
        $parameters = $method->getParameters();

        self::assertCount(2, $parameters);
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[0]->getType());
        self::assertSame(PasswordAuthenticatedUserInterface::class, $parameters[0]->getType()->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[1]->getType());
        self::assertSame('string', $parameters[1]->getType()->getName());
        self::assertInstanceOf(ReflectionNamedType::class, $method->getReturnType());
        self::assertSame('void', $method->getReturnType()->getName());
    }

    public function testActiveUserCheckerStillMatchesSymfony81Interface(): void
    {
        self::assertTrue(is_subclass_of(ActiveUserChecker::class, UserCheckerInterface::class));

        $method = new ReflectionMethod(ActiveUserChecker::class, 'checkPostAuth');
        $parameters = $method->getParameters();

        self::assertCount(2, $parameters);
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[1]->getType());
        self::assertSame(TokenInterface::class, $parameters[1]->getType()->getName());
        self::assertTrue($parameters[1]->getType()->allowsNull());
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertNull($parameters[1]->getDefaultValue());
    }


    public function testProjectVoterMatchesSymfony81Signature(): void
    {
        self::assertTrue(is_subclass_of(ProjectVoter::class, Voter::class));

        $method = new ReflectionMethod(ProjectVoter::class, 'voteOnAttribute');
        $parameters = $method->getParameters();

        self::assertCount(4, $parameters);
        self::assertInstanceOf(ReflectionNamedType::class, $parameters[3]->getType());
        self::assertSame(Vote::class, $parameters[3]->getType()->getName());
        self::assertTrue($parameters[3]->getType()->allowsNull());
        self::assertTrue($parameters[3]->isDefaultValueAvailable());
        self::assertNull($parameters[3]->getDefaultValue());
    }

    public function testRepositoryDoesNotReferenceRemovedDoctrineBridgeNamespace(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Repository/UserRepository.php');

        self::assertIsString($source);
        self::assertStringNotContainsString(
            'Symfony\\Bridge\\Doctrine\\Security\\User\\PasswordUpgraderInterface',
            $source,
        );
        self::assertStringContainsString(
            'Symfony\\Component\\Security\\Core\\User\\PasswordUpgraderInterface',
            $source,
        );
    }
}
