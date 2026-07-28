<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Enum\AuditAction;
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
                    if (null !== $this->entries->findRunningForUser($user)) {
                        throw new DomainException('Esiste già un timer attivo.');
                    }

                    $entry = (new TimeEntry())
                        ->setActivity($activity)
                        ->setUser($user)
                        ->setStartedAt($at ?? new DateTimeImmutable())
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
            return $this->transaction->execute(
                function () use ($user, $at): TimeEntry {
                    $entry = $this->entries->findRunningForUser($user);
                    if (null === $entry) {
                        throw new DomainException('Nessun timer attivo.');
                    }

                    $entry->stop($at);
                    if ($entry->getDurationMinutes() < 1) {
                        throw new DomainException('Il timer deve durare almeno un minuto.');
                    }

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
}
