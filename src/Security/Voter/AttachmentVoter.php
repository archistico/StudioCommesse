<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Attachment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Attachment> */
final class AttachmentVoter extends Voter
{
    public const VIEW = 'ATTACHMENT_VIEW';
    public const MANAGE = 'ATTACHMENT_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Attachment && in_array($attribute, [self::VIEW, self::MANAGE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            $vote?->addReason('Utente non autenticato.');

            return false;
        }

        if (self::VIEW === $attribute) {
            return true;
        }

        if ($subject->getProject()->isArchived()) {
            $vote?->addReason('I documenti di una commessa archiviata sono in sola lettura.');

            return false;
        }

        if ($user->isPartner()) {
            return true;
        }

        $userId = $user->getId();
        if (null === $userId) {
            return false;
        }

        if ($subject->getUploadedBy()->getId() === $userId || $subject->getProject()->getResponsible()?->getId() === $userId) {
            return true;
        }

        $activity = $subject->getActivity();

        return null !== $activity
            && ($activity->getAssignee()?->getId() === $userId || $activity->getCreatedBy()?->getId() === $userId);
    }
}
