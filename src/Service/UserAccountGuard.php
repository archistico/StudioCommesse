<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\UserRole;

final class UserAccountGuard
{
    public function validateDeletion(User $actor, User $target, int $activePartnerCount): ?string
    {
        if ($actor === $target || (null !== $actor->getId() && $actor->getId() === $target->getId())) {
            return 'Non puoi eliminare il tuo stesso account.';
        }
        if ($target->isActive() && UserRole::Partner === $target->getRole() && $activePartnerCount <= 1) {
            return 'Deve rimanere almeno un socio attivo.';
        }

        return null;
    }

    public function validateUpdate(
        User $actor,
        UserRole $previousRole,
        bool $previouslyActive,
        User $target,
        int $activePartnerCount,
    ): ?string {
        $sameAccount = $actor === $target
            || (null !== $actor->getId() && $actor->getId() === $target->getId());

        if ($sameAccount && (!$target->isActive() || UserRole::Partner !== $target->getRole())) {
            return 'Non puoi disattivare o retrocedere il tuo stesso account.';
        }

        $wasActivePartner = $previouslyActive && UserRole::Partner === $previousRole;
        $remainsActivePartner = $target->isActive() && UserRole::Partner === $target->getRole();

        if ($wasActivePartner && !$remainsActivePartner && $activePartnerCount <= 1) {
            return 'Deve rimanere almeno un socio attivo.';
        }

        return null;
    }
}
