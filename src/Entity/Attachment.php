<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttachmentClassification;
use App\Repository\AttachmentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
#[ORM\Table(name: 'attachment')]
#[ORM\UniqueConstraint(name: 'uniq_attachment_storage_key', columns: ['storage_key'])]
#[ORM\Index(columns: ['project_id', 'created_at'], name: 'idx_attachment_project_created')]
#[ORM\Index(columns: ['activity_id', 'created_at'], name: 'idx_attachment_activity_created')]
#[ORM\Index(columns: ['classification'], name: 'idx_attachment_classification')]
#[ORM\Index(columns: ['uploaded_by_id'], name: 'idx_attachment_uploaded_by')]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'project_id', nullable: false, onDelete: 'CASCADE')]
    private Project $project;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'activity_id', nullable: true, onDelete: 'SET NULL')]
    private ?Activity $activity;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'uploaded_by_id', nullable: false, onDelete: 'RESTRICT')]
    private User $uploadedBy;

    #[ORM\Column(length: 32, enumType: AttachmentClassification::class)]
    private AttachmentClassification $classification;

    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 255)]
    private string $storageKey;

    #[ORM\Column(length: 127)]
    private string $mimeType;

    #[ORM\Column]
    private int $sizeBytes;

    #[ORM\Column(length: 64)]
    private string $sha256;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $description;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    public function __construct(
        Project $project,
        ?Activity $activity,
        User $uploadedBy,
        AttachmentClassification $classification,
        string $originalName,
        string $storageKey,
        string $mimeType,
        int $sizeBytes,
        string $sha256,
        ?string $description = null,
    ) {
        $this->project = $project;
        $this->activity = $activity;
        $this->uploadedBy = $uploadedBy;
        $this->classification = $classification;
        $this->originalName = trim($originalName);
        $this->storageKey = $storageKey;
        $this->mimeType = $mimeType;
        $this->sizeBytes = max(0, $sizeBytes);
        $this->sha256 = strtolower($sha256);
        $this->description = self::normalizeNullable($description);
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getProject(): Project { return $this->project; }
    public function getActivity(): ?Activity { return $this->activity; }
    public function setActivity(?Activity $activity): self { $this->activity = $activity; return $this; }
    public function getUploadedBy(): User { return $this->uploadedBy; }
    public function getClassification(): AttachmentClassification { return $this->classification; }
    public function setClassification(AttachmentClassification $classification): self { $this->classification = $classification; return $this; }
    public function getOriginalName(): string { return $this->originalName; }
    public function getStorageKey(): string { return $this->storageKey; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function getSha256(): string { return $this->sha256; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = self::normalizeNullable($description); return $this; }
    public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }

    #[Assert\Callback]
    public function validateActivityProject(ExecutionContextInterface $context): void
    {
        if (null !== $this->activity && $this->activity->getProject() !== $this->project) {
            $context->buildViolation('L’attività selezionata non appartiene alla commessa del documento.')
                ->atPath('activity')
                ->addViolation();
        }
    }

    private static function normalizeNullable(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
