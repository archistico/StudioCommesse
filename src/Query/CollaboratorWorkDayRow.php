<?php

declare(strict_types=1);

namespace App\Query;

use DateTimeImmutable;

final readonly class CollaboratorWorkDayRow
{
    /** @param list<CollaboratorWorkEntryRow> $entries */
    public function __construct(
        public DateTimeImmutable $date,
        public int $totalMinutes,
        public int $billableMinutes,
        public array $entries,
    ) {
    }
}
