<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\AuditAction;
use DateTimeImmutable;

final readonly class AuditSearchCriteria
{
    public function __construct(
        public ?string $group = null,
        public ?AuditAction $action = null,
        public ?string $actor = null,
        public ?string $requestId = null,
        public ?DateTimeImmutable $occurredFrom = null,
        public ?DateTimeImmutable $occurredBefore = null,
        public int $page = 1,
        public int $perPage = 50,
    ) {
    }
}
