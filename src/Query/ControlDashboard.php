<?php

declare(strict_types=1);

namespace App\Query;

final readonly class ControlDashboard
{
    /**
     * @param list<ProjectControlRow> $projects
     * @param list<CollaboratorControlRow> $collaborators
     * @param list<ClientControlRow> $clients
     * @param list<PeriodControlRow> $periods
     */
    public function __construct(
        public array $projects,
        public array $collaborators,
        public array $clients,
        public array $periods,
        public int $overallClosedCount,
        public int $toCollectCount,
        public int $criticalProjectCount,
        public int $overloadedCollaboratorCount,
    ) {
    }
}
