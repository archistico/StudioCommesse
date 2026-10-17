<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Attachment;
use App\Entity\Expense;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Enum\UserRole;
use App\Repository\TimeEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use DomainException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class WorkDeletionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TimeEntryRepository $entries,
        private TimerMutationLock $mutationLock,
        private AuditedTransaction $transaction,
    ) {
    }

    /** @return array{entry_count: int, running_count: int, total_minutes: int, total_cost_cents: int, attachment_count: int, expense_count: int} */
    public function activityImpact(Activity $activity): array
    {
        return $this->entries->deletionImpactForActivity($activity) + [
            'attachment_count' => $this->entityManager->getRepository(Attachment::class)->count(['activity' => $activity]),
            'expense_count' => $this->entityManager->getRepository(Expense::class)->count(['activity' => $activity]),
        ];
    }

    public function deleteEntry(TimeEntry $entry, User $actor, ?string $ipAddress = null): void
    {
        $this->assertPartner($actor);
        $entryId = $entry->getId();
        $lock = $this->mutationLock->acquireExclusive();
        try {
            $this->transaction->execute(
                function () use ($entry): array {
                    $this->refresh($entry);
                    $activity = $entry->getActivity();
                    $project = $this->requireWritableProject($activity?->getProject());
                    $details = [
                        'project_id' => $project->getId(),
                        'activity_id' => $activity?->getId(),
                        'user_id' => $entry->getUser()?->getId(),
                        'started_at' => $entry->getStartedAt()->format(DATE_ATOM),
                        'ended_at' => $entry->getEndedAt()?->format(DATE_ATOM),
                        'was_running' => $entry->isRunning(),
                        'minutes' => $entry->isRunning() ? 0 : $entry->getDurationMinutes(),
                        'cost_cents' => $entry->getCostSnapshotCents(),
                    ];
                    $this->entityManager->remove($entry);

                    return $details;
                },
                static fn (array $details): AuditRecord => new AuditRecord(
                    AuditAction::TimeEntryDeleted,
                    $actor->getUserIdentifier(),
                    TimeEntry::class,
                    $entryId,
                    $details,
                    $ipAddress,
                ),
            );
        } finally {
            $lock->release();
        }
    }

    public function deleteActivity(Activity $activity, User $actor, ?string $ipAddress = null): void
    {
        $this->assertPartner($actor);
        $activityId = $activity->getId();
        $lock = $this->mutationLock->acquireExclusive();
        try {
            $this->transaction->execute(
                function () use ($activity): array {
                    $this->refresh($activity);
                    $project = $this->requireWritableProject($activity->getProject());
                    $details = $this->activityImpact($activity) + [
                        'project_id' => $project->getId(),
                        'activity_id' => $activity->getId(),
                        'title' => $activity->getTitle(),
                    ];
                    // Keep Doctrine's managed references coherent across both audit flushes.
                    foreach ($this->entries->findForActivity($activity) as $entry) {
                        $this->entityManager->remove($entry);
                    }
                    foreach ($this->entityManager->getRepository(Expense::class)->findBy(['activity' => $activity]) as $expense) {
                        $expense->setActivity(null);
                    }
                    foreach ($this->entityManager->getRepository(Attachment::class)->findBy(['activity' => $activity]) as $attachment) {
                        $attachment->setActivity(null);
                    }
                    $this->entityManager->remove($activity);

                    return $details;
                },
                static fn (array $details): AuditRecord => new AuditRecord(
                    AuditAction::ActivityDeleted,
                    $actor->getUserIdentifier(),
                    Activity::class,
                    $activityId,
                    $details,
                    $ipAddress,
                ),
            );
        } finally {
            $lock->release();
        }
    }

    private function assertPartner(User $actor): void
    {
        if (!$actor->isActive() || UserRole::Partner !== $actor->getRole()) {
            throw new AccessDeniedException('Solo i soci possono eliminare attività e registrazioni ore.');
        }
    }

    private function requireWritableProject(?Project $project): Project
    {
        if (null === $project) {
            throw new \LogicException('Commessa mancante.');
        }
        $this->entityManager->refresh($project);
        if ($project->isArchived()) {
            throw new DomainException('La commessa è archiviata e i suoi dati sono in sola lettura.');
        }

        return $project;
    }

    private function refresh(Activity|TimeEntry $entity): void
    {
        try {
            $this->entityManager->refresh($entity);
        } catch (EntityNotFoundException $exception) {
            throw new NotFoundHttpException('Il dato è già stato eliminato.', $exception);
        }
    }
}
