<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\User;
use App\Service\HourlyRateResolver;
use PHPUnit\Framework\TestCase;

final class HourlyRateResolverTest extends TestCase
{
    public function testSpecificityOrderIsActivityProjectUserApplication(): void
    {
        $project = (new Project())->setDefaultHourlyRateCents(6000);
        $user = (new User())->setDefaultHourlyRateCents(4500);
        $activity = (new Activity())->setProject($project)->setHourlyRateOverrideCents(8000);
        $resolver = new HourlyRateResolver(3500);

        self::assertSame(8000, $resolver->resolve($activity, $user));

        $activity->setHourlyRateOverrideCents(null);
        self::assertSame(6000, $resolver->resolve($activity, $user));

        $project->setDefaultHourlyRateCents(0);
        self::assertSame(4500, $resolver->resolve($activity, $user));

        $user->setDefaultHourlyRateCents(0);
        self::assertSame(3500, $resolver->resolve($activity, $user));
    }
}
