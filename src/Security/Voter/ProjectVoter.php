<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Project> */
final class ProjectVoter extends Voter
{
    public const EDIT = 'PROJECT_EDIT';
    public const VIEW_PRIVATE = 'PROJECT_VIEW_PRIVATE';
    public const VIEW_FINANCIAL = 'PROJECT_VIEW_FINANCIAL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Project && in_array($attribute, [self::EDIT, self::VIEW_PRIVATE, self::VIEW_FINANCIAL], true);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        $user = $token->getUser();
        if (!$user instanceof User) {
            $vote?->addReason('Utente non autenticato.');

            return false;
        }

        if (self::EDIT === $attribute && $subject->isArchived()) {
            $vote?->addReason('Ripristinare la commessa prima di modificarla.');

            return false;
        }

        if ($user->isPartner()) {
            return true;
        }

        $responsibleId = $subject->getResponsible()?->getId();
        $userId = $user->getId();
        if (null === $responsibleId || null === $userId || $responsibleId !== $userId) {
            $vote?->addReason('La commessa è assegnata a un altro responsabile.');

            return false;
        }

        return true;
    }
}
