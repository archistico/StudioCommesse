<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Repository\TimeEntryRepository;
use DateTimeImmutable;
use DomainException;

final readonly class TimerService
{
    public function __construct(
        private TimeEntryRepository $entries,
        private HourlyRateResolver $hourlyRateResolver,
    ) {
    }

    public function start(Activity $activity, User $user, ?DateTimeImmutable $at = null): TimeEntry
    {
        if (null !== $this->entries->findRunningForUser($user)) {
            throw new DomainException('Esiste già un timer attivo.');
        }

        $entry = (new TimeEntry())
            ->setActivity($activity)
            ->setUser($user)
            ->setStartedAt($at ?? new DateTimeImmutable())
            ->applyRateSnapshot($this->hourlyRateResolver->resolve($activity, $user));

        $this->entries->save($entry, true);

        return $entry;
    }

    public function stop(User $user, ?DateTimeImmutable $at = null): TimeEntry
    {
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
        $this->entries->save($entry, true);

        return $entry;
    }
}
