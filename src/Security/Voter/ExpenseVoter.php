<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Expense;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/** @extends Voter<string, Expense> */
final class ExpenseVoter extends Voter
{
    public const MANAGE = 'EXPENSE_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof Expense;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            $vote?->addReason('Utente non autenticato.');

            return false;
        }

        $project = $subject->getProject();
        if (null === $project || $project->isArchived()) {
            $vote?->addReason('Le spese di una commessa archiviata sono in sola lettura.');

            return false;
        }

        if ($user->isPartner()) {
            return true;
        }

        $userId = $user->getId();

        return null !== $userId && $subject->getRecordedBy()?->getId() === $userId;
    }
}
