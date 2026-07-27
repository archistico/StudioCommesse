<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\Index(columns: ['active', 'role'], name: 'idx_app_user_active_role')]
#[ORM\UniqueConstraint(name: 'uniq_app_user_username', columns: ['username'])]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['username'], message: 'Questo nome utente è già utilizzato.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Il nome è obbligatorio.')]
    #[Assert\Length(max: 120)]
    private string $displayName = '';

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Il nome utente è obbligatorio.')]
    #[Assert\Length(min: 3, max: 120)]
    #[Assert\Regex(
        pattern: '/^[a-z0-9._-]+$/',
        message: 'Usare solo lettere minuscole, numeri, punto, trattino e underscore.'
    )]
    private string $username = '';

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 32, enumType: UserRole::class)]
    private UserRole $role = UserRole::Collaborator;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $defaultHourlyRateCents = 0;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function setDisplayName(string $displayName): self
    {
        $this->displayName = trim($displayName);

        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = mb_strtolower(trim($username));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        if ('' === $this->username) {
            throw new \LogicException('Il nome utente deve essere valorizzato prima di usare l’utente per l’autenticazione.');
        }

        return $this->username;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return [$this->role->value];
    }

    public function getRole(): UserRole
    {
        return $this->role;
    }

    public function setRole(UserRole $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
        // Nessun dato sensibile temporaneo è memorizzato nell'entità.
    }

    public function getDefaultHourlyRateCents(): int { return $this->defaultHourlyRateCents; }
    public function setDefaultHourlyRateCents(int $value): self { $this->defaultHourlyRateCents = max(0, $value); return $this; }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isPartner(): bool
    {
        return UserRole::Partner === $this->role;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
