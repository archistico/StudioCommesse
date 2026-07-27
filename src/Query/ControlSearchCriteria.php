<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\OverallClosureStatus;
use DateTimeImmutable;

final readonly class ControlSearchCriteria
{
    public const SORT_CRITICALITY = 'criticality';
    public const SORT_DUE_DATE = 'due_date';
    public const SORT_CODE = 'code';
    public const SORT_ACTUAL_HOURS = 'actual_hours';
    public const SORT_TIME_DEVIATION = 'time_deviation';
    public const SORT_MARGIN = 'margin';

    /** @var list<string> */
    public const SORTS = [
        self::SORT_CRITICALITY,
        self::SORT_DUE_DATE,
        self::SORT_CODE,
        self::SORT_ACTUAL_HOURS,
        self::SORT_TIME_DEVIATION,
        self::SORT_MARGIN,
    ];

    public function __construct(
        public ?int $clientId = null,
        public ?int $responsibleId = null,
        public ?OverallClosureStatus $overallStatus = null,
        public bool $onlyCritical = false,
        public DateTimeImmutable $periodFrom = new DateTimeImmutable('first day of -5 months midnight'),
        public DateTimeImmutable $periodBefore = new DateTimeImmutable('tomorrow midnight'),
        public string $sort = self::SORT_CRITICALITY,
        public string $direction = 'desc',
    ) {
    }

    public function isDescending(): bool
    {
        return 'desc' === $this->direction;
    }
}
