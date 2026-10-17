<?php

declare(strict_types=1);

namespace App\Query;

use DateTimeImmutable;

final readonly class CollaboratorReportCriteria
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        public ?int $userId = null,
        public ?int $projectId = null,
    ) {
    }

    public function before(): DateTimeImmutable
    {
        return $this->to->modify('+1 day');
    }
}
