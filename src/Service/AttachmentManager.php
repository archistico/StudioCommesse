<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Attachment;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\AttachmentValidationException;
use App\Model\AttachmentUpload;
use Doctrine\ORM\EntityManagerInterface;

final readonly class AttachmentManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AttachmentStorage $storage,
    ) {
    }

    public function create(Project $project, User $uploadedBy, AttachmentUpload $upload): Attachment
    {
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

        try {
            $this->entityManager->persist($attachment);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            $this->storage->delete($stored->storageKey);
            throw $exception;
        }

        return $attachment;
    }

    public function updateMetadata(Attachment $attachment): void
    {
        $activity = $attachment->getActivity();
        if (null !== $activity && $activity->getProject() !== $attachment->getProject()) {
            throw new AttachmentValidationException('L’attività selezionata non appartiene alla commessa.');
        }

        $this->entityManager->flush();
    }

    public function delete(Attachment $attachment): void
    {
        $storageKey = $attachment->getStorageKey();
        $this->entityManager->remove($attachment);
        $this->entityManager->flush();
        $this->storage->delete($storageKey);
    }
}
