<?php

declare(strict_types=1);

namespace App\Query;

final readonly class DashboardSummary
{
    public function __construct(
        public int $openProjects,
        public int $waitingProjects,
        public int $overdueProjects,
        public int $activeClients,
        public int $openActivities,
        public int $overdueActivities,
        public int $workedMinutes,
        public int $activeUsers,
        public int $activePartners,
        public int $activeCollaborators,
    ) {
    }
}
