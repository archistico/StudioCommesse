<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Activity;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Activity> */
final class ActivityVoter extends Voter
{
    public const EDIT = 'ACTIVITY_EDIT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::EDIT === $attribute && $subject instanceof Activity;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || $subject->getProject()?->isArchived()) {
            return false;
        }

        if ($user->isPartner()) {
            return true;
        }

        $userId = $user->getId();

        return null !== $userId && (
            $subject->getAssignee()?->getId() === $userId
            || $subject->getCreatedBy()?->getId() === $userId
            || $subject->getProject()?->getResponsible()?->getId() === $userId
        );
    }
}
