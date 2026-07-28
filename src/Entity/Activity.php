<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ActivityPriority;
use App\Enum\ActivityStatus;
use App\Repository\ActivityRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'activity')]
#[ORM\Index(columns: ['project_id', 'status'], name: 'idx_activity_project_status')]
#[ORM\Index(columns: ['assignee_id', 'status'], name: 'idx_activity_assignee_status')]
#[ORM\Index(columns: ['due_at'], name: 'idx_activity_due_at')]
#[ORM\Index(columns: ['assignee_id', 'status', 'due_at'], name: 'idx_activity_assignee_status_due')]
#[ORM\Index(columns: ['updated_at'], name: 'idx_activity_updated_at')]
#[ORM\HasLifecycleCallbacks]
class Activity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(name:'project_id', nullable:false, onDelete:'CASCADE')]
    #[Assert\NotNull] private ?Project $project = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(name:'assignee_id', nullable:false, onDelete:'RESTRICT')]
    #[Assert\NotNull(message:'L’assegnatario è obbligatorio.')] private ?User $assignee = null;
    #[ORM\ManyToOne] #[ORM\JoinColumn(name:'created_by_id', nullable:false, onDelete:'RESTRICT')]
    private ?User $createdBy = null;
    #[ORM\Column(length:180)] #[Assert\NotBlank] #[Assert\Length(max:180)] private string $title = '';
    #[ORM\Column(type:'text', nullable:true)] #[Assert\Length(max:8000)] private ?string $description = null;
    #[ORM\Column(length:32, enumType:ActivityStatus::class)] private ActivityStatus $status = ActivityStatus::NotStarted;
    #[ORM\Column(length:24, enumType:ActivityPriority::class)] private ActivityPriority $priority = ActivityPriority::Normal;
    #[ORM\Column] #[Assert\Range(min:0,max:100)] private int $progressPercent = 0;
    #[ORM\Column(nullable:true)] #[Assert\PositiveOrZero] private ?int $initialEstimatedMinutes = null;
    #[ORM\Column(nullable:true)] #[Assert\PositiveOrZero] private ?int $remainingEstimatedMinutes = null;
    #[ORM\Column(nullable:true)] #[Assert\PositiveOrZero] private ?int $hourlyRateOverrideCents = null;
    #[ORM\Column(type:Types::DATETIME_IMMUTABLE, nullable:true)] private ?DateTimeImmutable $startAt = null;
    #[ORM\Column(type:Types::DATETIME_IMMUTABLE, nullable:true)] private ?DateTimeImmutable $dueAt = null;
    #[ORM\Column(type:Types::DATETIME_IMMUTABLE, nullable:true)] private ?DateTimeImmutable $completedAt = null;
    #[ORM\Column] private DateTimeImmutable $createdAt;
    #[ORM\Column] private DateTimeImmutable $updatedAt;

    public function __construct(){ $this->createdAt=$this->updatedAt=new DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getProject(): ?Project { return $this->project; }
    public function setProject(?Project $v): self { $this->project=$v; return $this; }
    public function getAssignee(): ?User { return $this->assignee; }
    public function setAssignee(?User $v): self { $this->assignee=$v; return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function setCreatedBy(?User $v): self { $this->createdBy=$v; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $v): self { $this->title=trim($v); return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $v): self { $this->description=self::nullable($v); return $this; }
    public function getStatus(): ActivityStatus { return $this->status; }
    public function setStatus(ActivityStatus $v): self { $this->status=$v; $this->applyWorkflow(); return $this; }
    public function getPriority(): ActivityPriority { return $this->priority; }
    public function setPriority(ActivityPriority $v): self { $this->priority=$v; return $this; }
    public function getProgressPercent(): int { return $this->progressPercent; }
    public function setProgressPercent(int $v): self { $this->progressPercent=max(0,min(100,$v)); return $this; }
    public function getInitialEstimatedMinutes(): ?int { return $this->initialEstimatedMinutes; }
    public function setInitialEstimatedMinutes(?int $v): self { $this->initialEstimatedMinutes=$v; return $this; }
    public function getRemainingEstimatedMinutes(): ?int { return $this->remainingEstimatedMinutes; }
    public function setRemainingEstimatedMinutes(?int $v): self { $this->remainingEstimatedMinutes=$v; return $this; }
    public function getHourlyRateOverrideCents(): ?int { return $this->hourlyRateOverrideCents; }
    public function setHourlyRateOverrideCents(?int $value): self { $this->hourlyRateOverrideCents = null === $value ? null : max(0, $value); return $this; }

    public function getStartAt(): ?DateTimeImmutable { return $this->startAt; }
    public function setStartAt(?DateTimeImmutable $v): self { $this->startAt=$v; return $this; }
    public function getDueAt(): ?DateTimeImmutable { return $this->dueAt; }
    public function setDueAt(?DateTimeImmutable $v): self { $this->dueAt=$v; return $this; }
    public function getCompletedAt(): ?DateTimeImmutable { return $this->completedAt; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function isOverdue(?DateTimeImmutable $now=null): bool { return null!==$this->dueAt && !$this->status->isClosed() && $this->dueAt < ($now??new DateTimeImmutable()); }
    #[Assert\Callback] public function validateDates(ExecutionContextInterface $c): void { if(null!==$this->startAt && null!==$this->dueAt && $this->dueAt<$this->startAt){$c->buildViolation('La scadenza non può precedere l’inizio.')->atPath('dueAt')->addViolation();} }
    #[ORM\PrePersist, ORM\PreUpdate] public function normalizeWorkflow(): void { $this->applyWorkflow(); $this->updatedAt=new DateTimeImmutable(); }
    private function applyWorkflow(): void { if(ActivityStatus::InProgress===$this->status){$this->startAt??=new DateTimeImmutable();} if(ActivityStatus::Completed===$this->status){$this->completedAt??=new DateTimeImmutable();$this->progressPercent=100;$this->remainingEstimatedMinutes=0;} elseif(ActivityStatus::Cancelled===$this->status){$this->completedAt=null;} else {$this->completedAt=null;if($this->progressPercent===100){$this->progressPercent=99;}} }
    private static function nullable(?string $v): ?string { if(null===$v)return null; $v=trim($v); return ''===$v?null:$v; }
}
