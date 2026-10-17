<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\AuditAction;
use App\Exception\TimerTooShortException;
use App\Repository\TimeEntryRepository;
use DateTimeImmutable;
use DomainException;

final readonly class TimerService
{
    public function __construct(
        private TimeEntryRepository $entries,
        private HourlyRateResolver $hourlyRateResolver,
        private TimerMutationLock $mutationLock,
        private AuditedTransaction $transaction,
    ) {
    }

    public function start(Activity $activity, User $user, ?DateTimeImmutable $at = null, ?string $ipAddress = null): TimeEntry
    {
        $lock = $this->mutationLock->acquireExclusive();
        try {
            return $this->transaction->execute(
                function () use ($activity, $user, $at): TimeEntry {
                    if ($activity->getProject()?->isArchived()) {
                        throw new DomainException('La commessa è archiviata.');
                    }

                    if (null !== $this->entries->findRunningForUser($user)) {
                        throw new DomainException('Esiste già un timer attivo.');
                    }

                    $startedAt = $at ?? new DateTimeImmutable();
                    if ($this->entries->overlaps($user, $startedAt, null)) {
                        throw new DomainException('L’intervallo si sovrappone a un’altra registrazione.');
                    }

                    $entry = (new TimeEntry())
                        ->setActivity($activity)
                        ->setUser($user)
                        ->setStartedAt($startedAt)
                        ->applyRateSnapshot($this->hourlyRateResolver->resolve($activity, $user));

                    $this->entries->save($entry, false);

                    return $entry;
                },
                static fn (TimeEntry $entry): AuditRecord => new AuditRecord(
                    AuditAction::TimerStarted,
                    $user->getUserIdentifier(),
                    TimeEntry::class,
                    $entry->getId(),
                    ['activity' => $activity->getTitle()],
                    $ipAddress,
                ),
            );
        } finally {
            $lock->release();
        }
    }

    public function stop(User $user, ?DateTimeImmutable $at = null, ?string $ipAddress = null): TimeEntry
    {
        $lock = $this->mutationLock->acquireExclusive();
        try {
            $entry = $this->entries->findRunningForUser($user);
            if (null === $entry) {
                throw new DomainException('Nessun timer attivo.');
            }
            $stoppedAt = $at ?? new DateTimeImmutable();
            if ($entry->getDurationMinutes($stoppedAt) < 1) {
                throw new TimerTooShortException($entry);
            }

            return $this->transaction->execute(
                function () use ($entry, $stoppedAt): TimeEntry {
                    $entry->stop($stoppedAt);
                    $activity = $entry->getActivity();
                    if (null === $activity) {
                        throw new \LogicException('Il timer non è associato a un’attività.');
                    }

                    $entry->recalculateCostFromSnapshot();
                    $this->entries->save($entry, false);

                    return $entry;
                },
                static fn (TimeEntry $entry): AuditRecord => new AuditRecord(
                    AuditAction::TimerStopped,
                    $user->getUserIdentifier(),
                    TimeEntry::class,
                    $entry->getId(),
                    ['minutes' => $entry->getDurationMinutes()],
                    $ipAddress,
                ),
            );
        } finally {
            $lock->release();
        }
    }

    /** Discards only the exact running entry explicitly confirmed by its owner. */
    public function cancel(User $user, int $expectedEntryId, ?string $ipAddress = null): TimeEntry
    {
        $lock = $this->mutationLock->acquireExclusive();
        try {
            $entry = $this->entries->findRunningForUser($user);
            if (null === $entry || $entry->getId() !== $expectedEntryId) {
                throw new DomainException('La registrazione da annullare non è più in corso. Nessun altro timer è stato modificato.');
            }
            if ($entry->getActivity()?->getProject()?->isArchived()) {
                throw new DomainException('La commessa è archiviata.');
            }
            $entryId = $entry->getId();
            $activityId = $entry->getActivity()?->getId();
            $projectId = $entry->getActivity()?->getProject()?->getId();

            return $this->transaction->execute(
                function () use ($entry): TimeEntry {
                    $this->entries->remove($entry, false);

                    return $entry;
                },
                static fn (TimeEntry $cancelled): AuditRecord => new AuditRecord(
                    AuditAction::TimerCancelled,
                    $user->getUserIdentifier(),
                    TimeEntry::class,
                    $entryId,
                    ['activity_id' => $activityId, 'project_id' => $projectId, 'started_at' => $cancelled->getStartedAt()->format(DATE_ATOM)],
                    $ipAddress,
                ),
            );
        } finally {
            $lock->release();
        }
    }
}
