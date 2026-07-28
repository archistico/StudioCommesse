<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\AuditAction;

final readonly class AuditRecord
{
    /** @param array<string, bool|float|int|string|null> $details */
    public function __construct(
        public AuditAction $action,
        public ?string $actorIdentifier = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public array $details = [],
        public ?string $ipAddress = null,
    ) {
    }
}
