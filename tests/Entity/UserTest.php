<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testEmptyUsernameCannotBeUsedAsSecurityIdentifier(): void
    {
        $this->expectException(\LogicException::class);

        (new User())->getUserIdentifier();
    }

    public function testUsernameIsNormalizedAndRoleIsExposedToSymfony(): void
    {
        $user = (new User())
            ->setUsername('  Mario.Rossi  ')
            ->setRole(UserRole::Partner);

        self::assertSame('mario.rossi', $user->getUserIdentifier());
        self::assertSame([UserRole::Partner->value], $user->getRoles());
        self::assertTrue($user->isPartner());
    }

    public function testNewUserDefaultsToActiveCollaborator(): void
    {
        $user = new User();

        self::assertTrue($user->isActive());
        self::assertSame(UserRole::Collaborator, $user->getRole());
        self::assertFalse($user->isPartner());
    }
}
