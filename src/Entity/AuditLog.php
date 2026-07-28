<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AuditAction;
use App\Repository\AuditLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['occurred_at'], name: 'idx_audit_occurred_at')]
#[ORM\Index(columns: ['action'], name: 'idx_audit_action')]
#[ORM\Index(columns: ['actor_identifier', 'occurred_at'], name: 'idx_audit_actor_occurred')]
#[ORM\Index(columns: ['subject_type', 'subject_id', 'occurred_at'], name: 'idx_audit_subject_occurred')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, enumType: AuditAction::class)]
    private AuditAction $action;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $actorIdentifier;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $subjectType;

    #[ORM\Column(nullable: true)]
    private ?int $subjectId;

    /** @var array<string, bool|float|int|string|null> */
    #[ORM\Column(type: 'json')]
    private array $details;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress;

    #[ORM\Column]
    private DateTimeImmutable $occurredAt;

    /** @param array<string, bool|float|int|string|null> $details */
    public function __construct(
        AuditAction $action,
        ?string $actorIdentifier = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $details = [],
        ?string $ipAddress = null,
    ) {
        $this->action = $action;
        $this->actorIdentifier = $actorIdentifier;
        $this->subjectType = $subjectType;
        $this->subjectId = $subjectId;
        $this->details = $details;
        $this->ipAddress = $ipAddress;
        $this->occurredAt = new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): AuditAction
    {
        return $this->action;
    }

    public function getActorIdentifier(): ?string
    {
        return $this->actorIdentifier;
    }

    public function getSubjectType(): ?string
    {
        return $this->subjectType;
    }

    public function getSubjectId(): ?int
    {
        return $this->subjectId;
    }

    /** @return array<string, bool|float|int|string|null> */
    public function getDetails(): array
    {
        return $this->details;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getRequestId(): ?string
    {
        return $this->detailString('request_id');
    }

    public function getRoute(): ?string
    {
        return $this->detailString('route');
    }

    public function getHttpMethod(): ?string
    {
        return $this->detailString('method');
    }

    /** @return array<string, bool|float|int|string|null> */
    public function getVisibleDetails(): array
    {
        return array_diff_key($this->details, array_flip(['request_id', 'route', 'method']));
    }


    public function getActorLabel(): string
    {
        if (null !== $this->actorIdentifier && '' !== $this->actorIdentifier) {
            return $this->actorIdentifier;
        }

        return match ($this->action) {
            AuditAction::LoginFailed, AuditAction::LoginThrottled => 'Identificativo protetto',
            default => 'Sistema',
        };
    }

    public function getSubjectLabel(): string
    {
        if (null === $this->subjectType || '' === $this->subjectType) {
            return '—';
        }

        $parts = explode('\\', $this->subjectType);
        $label = (string) end($parts);

        return null === $this->subjectId ? $label : $label.' #'.$this->subjectId;
    }

    private function detailString(string $key): ?string
    {
        $value = $this->details[$key] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }
}
