<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<User> */
final readonly class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private UserRepository $users)
    {
    }

    public function loadUserByIdentifier(string $identifier): User
    {
        $user = $this->users->findOneBy(['username' => mb_strtolower(trim($identifier), 'UTF-8')]);
        if (!$user instanceof User) {
            throw new UserNotFoundException('Utente non disponibile.');
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): User
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException('Tipo di utente non supportato.');
        }

        $refreshed = null === $user->getId() ? null : $this->users->find($user->getId());
        if (!$refreshed instanceof User || !$refreshed->isActive()) {
            throw new UserNotFoundException('Utente non disponibile.');
        }

        return $refreshed;
    }

    public function supportsClass(string $class): bool
    {
        return is_a($class, User::class, true);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException('Tipo di utente non supportato.');
        }

        $this->users->upgradePassword($user, $newHashedPassword);
    }
}
