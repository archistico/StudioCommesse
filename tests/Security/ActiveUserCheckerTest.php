<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use App\Security\ActiveUserChecker;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionNamedType;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class ActiveUserCheckerTest extends TestCase
{
    public function testPostAuthenticationSignatureMatchesSymfonyEightContract(): void
    {
        $method = new ReflectionMethod(ActiveUserChecker::class, 'checkPostAuth');
        $parameters = $method->getParameters();

        self::assertCount(2, $parameters);
        self::assertSame('user', $parameters[0]->getName());
        self::assertSame('token', $parameters[1]->getName());
        $tokenType = $parameters[1]->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $tokenType);
        self::assertTrue($tokenType->allowsNull());
        self::assertSame(TokenInterface::class, $tokenType->getName());
        self::assertTrue($parameters[1]->isDefaultValueAvailable());
        self::assertNull($parameters[1]->getDefaultValue());
    }

    public function testInactiveUserIsRejectedBeforeAuthentication(): void
    {
        $user = (new User())->setActive(false);
        $checker = new ActiveUserChecker();

        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage('Questo account è stato disattivato.');

        $checker->checkPreAuth($user);
    }

    public function testPostAuthenticationAcceptsMissingOrExplicitToken(): void
    {
        $checker = new ActiveUserChecker();
        $user = new User();
        $token = $this->createStub(TokenInterface::class);

        $checker->checkPostAuth($user);
        $checker->checkPostAuth($user, $token);

        self::addToAssertionCount(1);
    }
}
