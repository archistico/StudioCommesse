<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Performance\CapacityProfile;
use PHPUnit\Framework\TestCase;

final class CapacityProfileTest extends TestCase
{
    public function testProfilesDefineDeterministicThirtyTwoHundredAndSixHundredProjectDatasets(): void
    {
        self::assertSame(30, CapacityProfile::Small->projectCount());
        self::assertSame(200, CapacityProfile::Small->activityCount());
        self::assertSame(600, CapacityProfile::Small->timeEntryCount());

        self::assertSame(200, CapacityProfile::Medium->projectCount());
        self::assertSame(1_400, CapacityProfile::Medium->activityCount());
        self::assertSame(4_200, CapacityProfile::Medium->timeEntryCount());

        self::assertSame(600, CapacityProfile::Large->projectCount());
        self::assertSame(4_200, CapacityProfile::Large->activityCount());
        self::assertSame(12_600, CapacityProfile::Large->timeEntryCount());
        self::assertSame(CapacityProfile::Large, CapacityProfile::fromProjectCount(600));
    }
}
