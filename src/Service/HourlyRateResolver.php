<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\User;

/**
 * Risolve la tariffa applicabile con la precedenza più specifica:
 * attività, commessa, collaboratore, tariffa generale dell'applicazione.
 */
final readonly class HourlyRateResolver
{
    public function __construct(private int $defaultHourlyRateCents = 0)
    {
    }

    public function resolve(Activity $activity, User $user): int
    {
        $activityRate = $activity->getHourlyRateOverrideCents();
        if (null !== $activityRate && $activityRate > 0) {
            return $activityRate;
        }

        $projectRate = $activity->getProject()?->getDefaultHourlyRateCents() ?? 0;
        if ($projectRate > 0) {
            return $projectRate;
        }

        $userRate = $user->getDefaultHourlyRateCents();
        if ($userRate > 0) {
            return $userRate;
        }

        return max(0, $this->defaultHourlyRateCents);
    }
}
