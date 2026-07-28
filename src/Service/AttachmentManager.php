<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\AuditLog;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\AttachmentValidationException;
use App\Model\AttachmentUpload;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final readonly class AttachmentManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AttachmentStorage $storage,
        private AttachmentMutationLock $mutationLock,
        private AuditLogger $auditLogger,
        private LoggerInterface $logger,
    ) {
    }

    /** @param null|callable(Attachment): AuditRecord $auditFactory */
    public function create(
        Project $project,
        User $uploadedBy,
        AttachmentUpload $upload,
        ?callable $auditFactory = null,
    ): Attachment {
        $lock = $this->mutationLock->acquireShared();
        try {
            if ($project->isArchived()) {
                throw new AttachmentValidationException('Non è possibile aggiungere documenti a una commessa archiviata.');
            }
            if (null === $upload->file) {
                throw new AttachmentValidationException('Selezionare un file.');
            }
            if (null !== $upload->activity && $upload->activity->getProject() !== $project) {
                throw new AttachmentValidationException('L’attività selezionata non appartiene alla commessa.');
            }

            $stored = $this->storage->store($upload->file);
            $attachment = new Attachment(
                project: $project,
                activity: $upload->activity,
                uploadedBy: $uploadedBy,
                classification: $upload->classification,
                originalName: $stored->originalName,
                storageKey: $stored->storageKey,
                mimeType: $stored->mimeType,
                sizeBytes: $stored->sizeBytes,
                sha256: $stored->sha256,
                description: $upload->description,
            );
            $auditEntry = null;

            try {
                $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use (
                    $attachment,
                    $auditFactory,
                    &$auditEntry,
                ): void {
                    $entityManager->persist($attachment);
                    $entityManager->flush();
                    $auditEntry = $this->recordAudit($attachment, $auditFactory);
                    if ($auditEntry instanceof AuditLog) {
                        $entityManager->flush();
                    }
                });
            } catch (\Throwable $exception) {
                $this->storage->delete($stored->storageKey);
                throw $exception;
            }

            if ($auditEntry instanceof AuditLog) {
                $this->auditLogger->mirror($auditEntry);
            }

            return $attachment;
        } finally {
            $lock->release();
        }
    }

    /** @param null|callable(Attachment): AuditRecord $auditFactory */
    public function updateMetadata(Attachment $attachment, ?callable $auditFactory = null): void
    {
        $activity = $attachment->getActivity();
        if (null !== $activity && $activity->getProject() !== $attachment->getProject()) {
            throw new AttachmentValidationException('L’attività selezionata non appartiene alla commessa.');
        }

        $auditEntry = null;
        $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use (
            $attachment,
            $auditFactory,
            &$auditEntry,
        ): void {
            $entityManager->flush();
            $auditEntry = $this->recordAudit($attachment, $auditFactory);
            if ($auditEntry instanceof AuditLog) {
                $entityManager->flush();
            }
        });

        if ($auditEntry instanceof AuditLog) {
            $this->auditLogger->mirror($auditEntry);
        }
    }

    /** @param null|callable(Attachment): AuditRecord $auditFactory */
    public function delete(Attachment $attachment, ?callable $auditFactory = null): void
    {
        $lock = $this->mutationLock->acquireShared();
        try {
            $quarantined = $this->storage->quarantine($attachment->getStorageKey());
            $auditEntry = null;

            try {
                $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use (
                    $attachment,
                    $auditFactory,
                    &$auditEntry,
                ): void {
                    $auditRecord = null !== $auditFactory ? $auditFactory($attachment) : null;
                    $entityManager->remove($attachment);
                    $entityManager->flush();
                    if ($auditRecord instanceof AuditRecord) {
                        $auditEntry = $this->auditLogger->record($auditRecord);
                        $entityManager->flush();
                    }
                });
            } catch (\Throwable $exception) {
                if ($quarantined instanceof QuarantinedAttachment) {
                    $this->storage->restore($quarantined);
                }
                throw $exception;
            }

            if ($auditEntry instanceof AuditLog) {
                $this->auditLogger->mirror($auditEntry);
            }

            if ($quarantined instanceof QuarantinedAttachment) {
                try {
                    $this->storage->purge($quarantined);
                } catch (\Throwable $exception) {
                    // Il record è già stato eliminato: il file resta isolato fuori dallo storage attivo.
                    $this->logger->warning('File documentale rimasto nella quarantena dopo l’eliminazione.', [
                        'storage_key' => $quarantined->storageKey,
                        'exception_class' => $exception::class,
                    ]);
                }
            }
        } finally {
            $lock->release();
        }
    }

    /** @param null|callable(Attachment): AuditRecord $auditFactory */
    private function recordAudit(Attachment $attachment, ?callable $auditFactory): ?AuditLog
    {
        if (null === $auditFactory) {
            return null;
        }

        return $this->auditLogger->record($auditFactory($attachment));
    }
}
