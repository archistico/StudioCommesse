<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\AuditAction;

final readonly class MonthlyActionReportRow
{
    public function __construct(
        public AuditAction $action,
        public int $count,
    ) {
    }
}
