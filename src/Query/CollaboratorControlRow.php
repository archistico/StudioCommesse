<?php

declare(strict_types=1);

namespace App\Query;

final readonly class CollaboratorControlRow
{
    public function __construct(
        public int $userId,
        public string $displayName,
        public string $roleLabel,
        public bool $active,
        public int $openActivities,
        public int $overdueActivities,
        public int $remainingMinutes,
        public int $workedMinutes,
        public int $billableMinutes,
        public int $projectCount,
        public bool $overloaded,
    ) {
    }
}
