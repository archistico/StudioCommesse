<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\AuditAction;
use DateTimeImmutable;

final readonly class MonthlyActionEventRow
{
    public function __construct(
        public int $id,
        public DateTimeImmutable $occurredAt,
        public AuditAction $action,
        public string $actor,
        public string $summary,
        public ?string $subjectType,
        public ?int $subjectId,
    ) {
    }
}
