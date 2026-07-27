<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\ProjectPriority;
use App\Enum\ProjectStatus;

final readonly class ProjectSearchCriteria
{
    public function __construct(
        public ?string $query = null,
        public ?ProjectStatus $status = null,
        public ?ProjectPriority $priority = null,
        public ?int $responsibleId = null,
        public bool $includeArchived = false,
    ) {
    }
}
