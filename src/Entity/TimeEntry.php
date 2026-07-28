<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TimeEntryRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: TimeEntryRepository::class)]
#[ORM\Table(name: 'time_entry')]
#[ORM\Index(columns: ['activity_id', 'started_at'], name: 'idx_time_entry_activity_started')]
#[ORM\Index(columns: ['user_id', 'started_at'], name: 'idx_time_entry_user_started')]
#[ORM\Index(columns: ['started_at', 'ended_at'], name: 'idx_time_entry_started_ended')]
#[ORM\Index(columns: ['updated_at'], name: 'idx_time_entry_updated_at')]
#[ORM\HasLifecycleCallbacks]
class TimeEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'activity_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Activity $activity = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $endedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 4000)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $billable = true;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $hourlyRateSnapshotCents = 0;

    #[ORM\Column(options: ['default' => 0])]
    #[Assert\PositiveOrZero]
    private int $costSnapshotCents = 0;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->startedAt = $now;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActivity(): ?Activity
    {
        return $this->activity;
    }

    public function setActivity(?Activity $activity): self
    {
        $this->activity = $activity;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndedAt(): ?DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(?DateTimeImmutable $endedAt): self
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        if (null === $description) {
            $this->description = null;

            return $this;
        }

        $description = trim($description);
        $this->description = '' === $description ? null : $description;

        return $this;
    }

    public function isBillable(): bool
    {
        return $this->billable;
    }

    public function setBillable(bool $billable): self
    {
        $this->billable = $billable;

        return $this;
    }

    public function getHourlyRateSnapshotCents(): int
    {
        return $this->hourlyRateSnapshotCents;
    }

    public function getCostSnapshotCents(): int
    {
        return $this->costSnapshotCents;
    }

    /**
     * Congela la tariffa applicabile alla registrazione. Le modifiche future
     * alle regole tariffarie non alterano il costo storico.
     */
    public function applyRateSnapshot(int $hourlyRateCents): self
    {
        $this->hourlyRateSnapshotCents = max(0, $hourlyRateCents);

        return $this->recalculateCostFromSnapshot();
    }

    /** Ricalcola soltanto durata × tariffa già congelata. */
    public function recalculateCostFromSnapshot(): self
    {
        if (null === $this->endedAt) {
            $this->costSnapshotCents = 0;

            return $this;
        }

        $this->costSnapshotCents = (int) round(
            $this->getDurationMinutes() * $this->hourlyRateSnapshotCents / 60,
            0,
            PHP_ROUND_HALF_UP,
        );

        return $this;
    }

    public function isRunning(): bool
    {
        return null === $this->endedAt;
    }

    public function getDurationMinutes(?DateTimeImmutable $now = null): int
    {
        $end = $this->endedAt ?? $now ?? new DateTimeImmutable();

        return max(0, (int) floor(($end->getTimestamp() - $this->startedAt->getTimestamp()) / 60));
    }

    public function stop(?DateTimeImmutable $at = null): self
    {
        $this->endedAt = $at ?? new DateTimeImmutable();

        return $this;
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
    public function validateInterval(ExecutionContextInterface $context): void
    {
        if (null !== $this->endedAt && $this->endedAt <= $this->startedAt) {
            $context->buildViolation('La fine deve essere successiva all’inizio.')
                ->atPath('endedAt')
                ->addViolation();
        }
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
