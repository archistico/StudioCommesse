<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Enum\UserRole;
use App\Service\UserAccountGuard;
use PHPUnit\Framework\TestCase;

final class UserAccountGuardTest extends TestCase
{
    private UserAccountGuard $guard;

    protected function setUp(): void
    {
        $this->guard = new UserAccountGuard();
    }

    public function testUserCannotDeactivateOwnAccount(): void
    {
        $partner = (new User())->setRole(UserRole::Partner)->setActive(false);

        $error = $this->guard->validateUpdate(
            $partner,
            UserRole::Partner,
            true,
            $partner,
            2,
        );

        self::assertSame('Non puoi disattivare o retrocedere il tuo stesso account.', $error);
    }

    public function testLastActivePartnerCannotBeDemoted(): void
    {
        $actor = (new User())->setRole(UserRole::Partner);
        $target = (new User())->setRole(UserRole::Collaborator);

        $error = $this->guard->validateUpdate(
            $actor,
            UserRole::Partner,
            true,
            $target,
            1,
        );

        self::assertSame('Deve rimanere almeno un socio attivo.', $error);
    }

    public function testAnotherPartnerCanBeDemotedWhenOneRemains(): void
    {
        $actor = (new User())->setRole(UserRole::Partner);
        $target = (new User())->setRole(UserRole::Collaborator);

        self::assertNull($this->guard->validateUpdate(
            $actor,
            UserRole::Partner,
            true,
            $target,
            2,
        ));
    }
}
