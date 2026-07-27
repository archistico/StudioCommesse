<?php

declare(strict_types=1);

namespace App\Query;

use DateTimeImmutable;

final readonly class MonthlyReport
{
    /**
     * @param list<MonthlyProjectReportRow> $projects
     * @param list<MonthlyTimeEntryReportRow> $timeEntries
     * @param list<MonthlyActionReportRow> $actions
     * @param list<MonthlyActionEventRow> $events
     */
    public function __construct(
        public DateTimeImmutable $month,
        public DateTimeImmutable $periodFrom,
        public DateTimeImmutable $periodBefore,
        public array $projects,
        public array $timeEntries,
        public array $actions,
        public array $events,
        public int $workedMinutes,
        public int $billableMinutes,
        public int $timeEntryCount,
        public int $contributorCount,
        public int $movedProjectCount,
        public int $inactiveProjectCount,
        public int $expenseCents,
        public int $paymentCents,
        public int $attachmentCount,
        public int $actionCount,
    ) {
    }
}
