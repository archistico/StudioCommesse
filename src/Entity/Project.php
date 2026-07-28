<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProjectPriority;
use App\Enum\ProjectStatus;
use App\Repository\ProjectRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'project')]
#[ORM\UniqueConstraint(name: 'uniq_project_code', columns: ['code'])]
#[ORM\Index(columns: ['archived_at', 'status'], name: 'idx_project_archived_status')]
#[ORM\Index(columns: ['due_date'], name: 'idx_project_due_date')]
#[ORM\Index(columns: ['responsible_id'], name: 'idx_project_responsible')]
#[ORM\Index(columns: ['client_id'], name: 'idx_project_client')]
#[ORM\Index(columns: ['archived_at', 'due_date', 'code'], name: 'idx_project_active_due_code')]
#[ORM\Index(columns: ['updated_at'], name: 'idx_project_updated_at')]
#[ORM\HasLifecycleCallbacks]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 16)]
    private string $code = '';

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Il nome della commessa è obbligatorio.')]
    #[Assert\Length(max: 180)]
    private string $name = '';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Il cliente è obbligatorio.')]
    private ?Client $client = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'responsible_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull(message: 'Il responsabile è obbligatorio.')]
    private ?User $responsible = null;

    #[ORM\Column(length: 32, enumType: ProjectStatus::class)]
    private ProjectStatus $status = ProjectStatus::NotStarted;

    #[ORM\Column(length: 24, enumType: ProjectPriority::class)]
    private ProjectPriority $priority = ProjectPriority::Normal;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 8000)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $dueDate = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $waitingReason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 4000)]
    private ?string $privateNote = null;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $estimatedAmountCents = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $defaultHourlyRateCents = 0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

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

    public function getCode(): string
    {
        return $this->code;
    }

    public function assignCode(string $code): void
    {
        if ('' !== $this->code) {
            throw new \LogicException('Il codice della commessa è già stato assegnato.');
        }

        if (1 !== preg_match('/^\d{4}-\d{3,}$/', $code)) {
            throw new \InvalidArgumentException('Formato del codice commessa non valido.');
        }

        $this->code = $code;
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getResponsible(): ?User
    {
        return $this->responsible;
    }

    public function setResponsible(?User $responsible): self
    {
        $this->responsible = $responsible;

        return $this;
    }

    public function getStatus(): ProjectStatus
    {
        return $this->status;
    }

    public function setStatus(ProjectStatus $status): self
    {
        $today = new DateTimeImmutable('today');
        $this->status = $status;

        if (ProjectStatus::NotStarted === $status) {
            $this->startDate = null;
        } elseif (ProjectStatus::InProgress === $status && null === $this->startDate) {
            $this->startDate = $today;
        }

        if (ProjectStatus::Completed === $status) {
            $this->completedAt ??= $today;
        } else {
            $this->completedAt = null;
        }

        return $this;
    }

    public function getPriority(): ProjectPriority
    {
        return $this->priority;
    }

    public function setPriority(ProjectPriority $priority): self
    {
        $this->priority = $priority;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = self::normalizeNullable($description);

        return $this;
    }

    public function getStartDate(): ?DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getDueDate(): ?DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?DateTimeImmutable $dueDate): self
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function getWaitingReason(): ?string
    {
        return $this->waitingReason;
    }

    public function setWaitingReason(?string $waitingReason): self
    {
        $this->waitingReason = self::normalizeNullable($waitingReason);

        return $this;
    }

    public function getPrivateNote(): ?string
    {
        return $this->privateNote;
    }

    public function setPrivateNote(?string $privateNote): self
    {
        $this->privateNote = self::normalizeNullable($privateNote);

        return $this;
    }

    public function getEstimatedAmountCents(): int { return $this->estimatedAmountCents; }
    public function setEstimatedAmountCents(int $value): self { $this->estimatedAmountCents = max(0, $value); return $this; }
    public function getDefaultHourlyRateCents(): int { return $this->defaultHourlyRateCents; }
    public function setDefaultHourlyRateCents(int $value): self { $this->defaultHourlyRateCents = max(0, $value); return $this; }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
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
        if (!$this->status->isClosed()) {
            throw new \DomainException('Solo una commessa completata o annullata può essere archiviata.');
        }

        $this->archivedAt = $at ?? new DateTimeImmutable();
    }

    public function restore(): void
    {
        if ($this->client?->isArchived()) {
            throw new \DomainException('Ripristinare prima il cliente della commessa.');
        }

        if (null === $this->responsible || !$this->responsible->isActive()) {
            throw new \DomainException('Assegnare un responsabile attivo prima di ripristinare la commessa.');
        }

        $this->archivedAt = null;
    }

    public function isOverdue(?DateTimeImmutable $today = null): bool
    {
        if (null === $this->dueDate || $this->status->isClosed() || $this->isArchived()) {
            return false;
        }

        return $this->dueDate < ($today ?? new DateTimeImmutable('today'));
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (null !== $this->startDate && null !== $this->dueDate && $this->dueDate < $this->startDate) {
            $context->buildViolation('La data prevista di fine non può precedere la data di inizio.')
                ->atPath('dueDate')
                ->addViolation();
        }
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function normalizeWorkflow(): void
    {
        $today = new DateTimeImmutable('today');

        if (ProjectStatus::NotStarted === $this->status) {
            $this->startDate = null;
        } elseif (ProjectStatus::InProgress === $this->status && null === $this->startDate) {
            $this->startDate = $today;
        }

        if (ProjectStatus::Completed === $this->status) {
            $this->completedAt ??= $today;
        } else {
            $this->completedAt = null;
        }

        if (ProjectStatus::Waiting !== $this->status) {
            $this->waitingReason = null;
        }

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
