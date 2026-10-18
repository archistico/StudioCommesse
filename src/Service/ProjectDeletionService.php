<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Attachment;
use App\Entity\AuditLog;
use App\Entity\Expense;
use App\Entity\Payment;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class ProjectDeletionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TimerMutationLock $timerLock,
        private AttachmentMutationLock $attachmentLock,
        private AttachmentStorage $storage,
        private AuditLogger $auditLogger,
        private LoggerInterface $logger,
    ) {
    }

    /** @return array{activity_count: int, entry_count: int, running_count: int, total_minutes: int, total_cost_cents: int, expense_count: int, payment_count: int, attachment_count: int} */
    public function impact(Project $project): array
    {
        $row = $this->entityManager->getConnection()->fetchAssociative(
            <<<'SQL'
SELECT COUNT(*) AS entry_count,
       COALESCE(SUM(CASE WHEN ended_at IS NULL THEN 1 ELSE 0 END), 0) AS running_count,
       COALESCE(SUM(CASE WHEN ended_at IS NULL THEN 0 ELSE MAX(0, CAST((strftime('%s', ended_at) - strftime('%s', started_at)) / 60 AS INTEGER)) END), 0) AS total_minutes,
       COALESCE(SUM(CASE WHEN ended_at IS NULL THEN 0 ELSE cost_snapshot_cents END), 0) AS total_cost_cents
FROM time_entry INNER JOIN activity ON activity.id = time_entry.activity_id
WHERE activity.project_id = :project_id
SQL,
            ['project_id' => $project->getId()],
        );

        return [
            'activity_count' => $this->entityManager->getRepository(Activity::class)->count(['project' => $project]),
            'entry_count' => (int) ($row['entry_count'] ?? 0),
            'running_count' => (int) ($row['running_count'] ?? 0),
            'total_minutes' => (int) ($row['total_minutes'] ?? 0),
            'total_cost_cents' => (int) ($row['total_cost_cents'] ?? 0),
            'expense_count' => $this->entityManager->getRepository(Expense::class)->count(['project' => $project]),
            'payment_count' => $this->entityManager->getRepository(Payment::class)->count(['project' => $project]),
            'attachment_count' => $this->entityManager->getRepository(Attachment::class)->count(['project' => $project]),
        ];
    }

    public function delete(Project $project, User $actor, ?string $ipAddress = null): void
    {
        if (!$actor->isActive() || UserRole::Partner !== $actor->getRole()) {
            throw new AccessDeniedException('Solo i soci attivi possono cancellare una commessa.');
        }

        $timerLock = $this->timerLock->acquireExclusive();
        try {
            $attachmentLock = $this->attachmentLock->acquireExclusive();
            try {
                $this->deleteLocked($project, $actor, $ipAddress);
            } finally {
                $attachmentLock->release();
            }
        } finally {
            $timerLock->release();
        }
    }

    private function deleteLocked(Project $project, User $actor, ?string $ipAddress): void
    {
        /** @var list<QuarantinedAttachment> $quarantined */
        $quarantined = [];
        try {
            $auditEntry = $this->entityManager->wrapInTransaction(function (EntityManagerInterface $entityManager) use ($project, $actor, $ipAddress, &$quarantined): AuditLog {
                try {
                    $entityManager->refresh($project);
                } catch (EntityNotFoundException $exception) {
                    throw new NotFoundHttpException('La commessa è già stata eliminata.', $exception);
                }
                if ($project->isArchived()) {
                    throw new \DomainException('Ripristinare la commessa prima di cancellarla.');
                }

                $record = new AuditRecord(
                    AuditAction::ProjectDeleted,
                    $actor->getUserIdentifier(),
                    Project::class,
                    $project->getId(),
                    $this->impact($project) + [
                        'code' => $project->getCode(),
                        'name' => $project->getName(),
                        'client_id' => $project->getClient()?->getId(),
                        'responsible_id' => $project->getResponsible()?->getId(),
                    ],
                    $ipAddress,
                );

                foreach ($entityManager->getRepository(Attachment::class)->findBy(['project' => $project]) as $attachment) {
                    $file = $this->storage->quarantine($attachment->getStorageKey());
                    if (null !== $file) {
                        $quarantined[] = $file;
                    }
                    $entityManager->remove($attachment);
                }
                $entries = $entityManager->getRepository(TimeEntry::class)->createQueryBuilder('entry')
                    ->join('entry.activity', 'activity')
                    ->where('activity.project = :project')
                    ->setParameter('project', $project)
                    ->getQuery()->getResult();
                /** @var list<TimeEntry> $entries */
                foreach ($entries as $entry) {
                    $entityManager->remove($entry);
                }
                foreach ([Expense::class, Payment::class, Activity::class] as $entityClass) {
                    foreach ($entityManager->getRepository($entityClass)->findBy(['project' => $project]) as $entity) {
                        $entityManager->remove($entity);
                    }
                }
                $entityManager->remove($project);

                return $this->auditLogger->record($record);
            });
        } catch (\Throwable $exception) {
            foreach (array_reverse($quarantined) as $file) {
                $this->storage->restore($file);
            }
            throw $exception;
        }

        foreach ($quarantined as $file) {
            try {
                $this->storage->purge($file);
            } catch (\Throwable $exception) {
                $this->logger->warning('File rimasto in quarantena dopo la cancellazione della commessa.', [
                    'storage_key' => $file->storageKey,
                    'exception_class' => $exception::class,
                ]);
            }
        }
        $this->auditLogger->mirror($auditEntry);
    }
}
