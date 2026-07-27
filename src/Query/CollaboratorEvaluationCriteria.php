<?php

declare(strict_types=1);

namespace App\Query;

use DateTimeImmutable;

final readonly class CollaboratorEvaluationCriteria
{
    public function __construct(
        public int $userId,
        public DateTimeImmutable $periodFrom,
        public DateTimeImmutable $periodBefore,
        public ?int $clientId = null,
        public ?int $responsibleId = null,
        public ?int $projectId = null,
        public ?bool $billable = null,
    ) {
    }
}
