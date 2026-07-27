<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ClientRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'client')]
#[ORM\Index(columns: ['archived_at', 'name'], name: 'idx_client_archived_name')]
#[ORM\HasLifecycleCallbacks]
class Client
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Il nome del cliente è obbligatorio.')]
    #[Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $contactPerson = null;

    #[ORM\Column(length: 180, nullable: true)]
    #[Assert\Email(message: 'Inserire un indirizzo e-mail valido.')]
    #[Assert\Length(max: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60)]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $address = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Assert\Length(max: 32)]
    private ?string $taxCode = null;

    #[ORM\Column(length: 32, nullable: true)]
    #[Assert\Length(max: 32)]
    private ?string $vatNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 4000)]
    private ?string $notes = null;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $archivedAt = null;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = trim($name);

        return $this;
    }

    public function getContactPerson(): ?string
    {
        return $this->contactPerson;
    }

    public function setContactPerson(?string $contactPerson): self
    {
        $this->contactPerson = self::normalizeNullable($contactPerson);

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $normalized = self::normalizeNullable($email);
        $this->email = null === $normalized ? null : mb_strtolower($normalized);

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = self::normalizeNullable($phone);

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = self::normalizeNullable($address);

        return $this;
    }

    public function getTaxCode(): ?string
    {
        return $this->taxCode;
    }

    public function setTaxCode(?string $taxCode): self
    {
        $normalized = self::normalizeNullable($taxCode);
        $this->taxCode = null === $normalized ? null : mb_strtoupper($normalized);

        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): self
    {
        $normalized = self::normalizeNullable($vatNumber);
        $this->vatNumber = null === $normalized ? null : mb_strtoupper($normalized);

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = self::normalizeNullable($notes);

        return $this;
    }

    public function isArchived(): bool
    {
        return null !== $this->archivedAt;
    }

    public function getArchivedAt(): ?DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function archive(?DateTimeImmutable $at = null): void
    {
        $this->archivedAt = $at ?? new DateTimeImmutable();
    }

    public function restore(): void
    {
        $this->archivedAt = null;
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

    private static function normalizeNullable(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
