<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ExpenseRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ORM\Table(name: 'expense')]
#[ORM\Index(columns: ['project_id', 'spent_on'], name: 'idx_expense_project_date')]
#[ORM\Index(columns: ['spent_on', 'project_id'], name: 'idx_expense_date_project')]
class Expense
{
    public const CATEGORIES = [
        'Viaggio',
        'Carburante',
        'Vitto',
        'Alloggio',
        'Materiali',
        'Stampa',
        'Consulenza',
        'Altro',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Project $project = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'activity_id', nullable: true, onDelete: 'SET NULL')]
    private ?Activity $activity = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'recorded_by_id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?User $recordedBy = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private DateTimeImmutable $spentOn;

    #[ORM\Column(length: 40)]
    #[Assert\Choice(callback: [self::class, 'getCategories'])]
    private string $category = 'Altro';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $description = '';

    #[ORM\Column]
    #[Assert\Positive]
    private int $amountCents = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $reimbursable = false;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->spentOn = new DateTimeImmutable('today');
        $this->createdAt = new DateTimeImmutable();
    }

    /** @return list<string> */
    public static function getCategories(): array
    {
        return self::CATEGORIES;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
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

    public function getRecordedBy(): ?User
    {
        return $this->recordedBy;
    }

    public function setRecordedBy(?User $recordedBy): self
    {
        $this->recordedBy = $recordedBy;

        return $this;
    }

    public function getSpentOn(): DateTimeImmutable
    {
        return $this->spentOn;
    }

    public function setSpentOn(DateTimeImmutable $spentOn): self
    {
        $this->spentOn = $spentOn;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = trim($category);

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = trim($description);

        return $this;
    }

    public function getAmountCents(): int
    {
        return $this->amountCents;
    }

    public function setAmountCents(int $amountCents): self
    {
        $this->amountCents = max(0, $amountCents);

        return $this;
    }

    public function isReimbursable(): bool
    {
        return $this->reimbursable;
    }

    public function setReimbursable(bool $reimbursable): self
    {
        $this->reimbursable = $reimbursable;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[Assert\Callback]
    public function validateActivityProject(ExecutionContextInterface $context): void
    {
        if (null !== $this->activity && $this->activity->getProject()?->getId() !== $this->project?->getId()) {
            $context->buildViolation('L’attività deve appartenere alla commessa selezionata.')
                ->atPath('activity')
                ->addViolation();
        }
    }
}
