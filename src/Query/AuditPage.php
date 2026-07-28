<?php

declare(strict_types=1);

namespace App\Query;

use App\Entity\AuditLog;

final readonly class AuditPage
{
    /** @param list<AuditLog> $items */
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {
    }
}
