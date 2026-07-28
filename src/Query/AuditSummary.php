<?php

declare(strict_types=1);

namespace App\Query;

final readonly class AuditSummary
{
    public function __construct(
        public int $totalEvents,
        public int $securityEvents,
        public int $failedLogins,
        public int $blockedLogins,
        public int $actorCount,
    ) {
    }
}
