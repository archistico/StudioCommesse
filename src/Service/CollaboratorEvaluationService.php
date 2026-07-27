<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Query\CollaboratorEvaluation;
use App\Query\CollaboratorEvaluationCriteria;
use App\Query\CollaboratorWorkDayRow;
use App\Query\CollaboratorWorkEntryRow;
use App\Repository\ControlRepository;
use DateTimeImmutable;

final readonly class CollaboratorEvaluationService
{
    public function __construct(private ControlRepository $repository)
    {
    }

    public function build(User $user, CollaboratorEvaluationCriteria $criteria): CollaboratorEvaluation
    {
        /** @var array<string, array{date: DateTimeImmutable, total_minutes: int, billable_minutes: int, entries: list<CollaboratorWorkEntryRow>}> $days */
        $days = [];
        $projectIds = [];
        $entryCount = 0;
        $totalMinutes = 0;
        $billableMinutes = 0;

        foreach ($this->repository->findCollaboratorWorkEntries($criteria) as $row) {
            $startedAt = new DateTimeImmutable((string) ($row['started_at'] ?? 'now'));
            $endedAt = new DateTimeImmutable((string) ($row['ended_at'] ?? 'now'));
            $durationMinutes = max(0, (int) ($row['duration_minutes'] ?? 0));
            $billable = 1 === (int) ($row['billable'] ?? 0);
            $dayKey = $startedAt->format('Y-m-d');

            $entry = new CollaboratorWorkEntryRow(
                timeEntryId: (int) ($row['time_entry_id'] ?? 0),
                projectId: (int) ($row['project_id'] ?? 0),
                projectCode: (string) ($row['project_code'] ?? ''),
                projectName: (string) ($row['project_name'] ?? ''),
                activityId: (int) ($row['activity_id'] ?? 0),
                activityTitle: (string) ($row['activity_title'] ?? ''),
                startedAt: $startedAt,
                endedAt: $endedAt,
                durationMinutes: $durationMinutes,
                billable: $billable,
                description: $this->nullableText($row['description'] ?? null),
            );

            if (!isset($days[$dayKey])) {
                $days[$dayKey] = [
                    'date' => $startedAt->setTime(0, 0),
                    'total_minutes' => 0,
                    'billable_minutes' => 0,
                    'entries' => [],
                ];
            }

            $days[$dayKey]['entries'][] = $entry;
            $days[$dayKey]['total_minutes'] += $durationMinutes;
            if ($billable) {
                $days[$dayKey]['billable_minutes'] += $durationMinutes;
                $billableMinutes += $durationMinutes;
            }

            $totalMinutes += $durationMinutes;
            ++$entryCount;
            $projectIds[$entry->projectId] = true;
        }

        krsort($days);
        $dayRows = array_map(
            static fn (array $day): CollaboratorWorkDayRow => new CollaboratorWorkDayRow(
                date: $day['date'],
                totalMinutes: $day['total_minutes'],
                billableMinutes: $day['billable_minutes'],
                entries: $day['entries'],
            ),
            array_values($days),
        );
        $workedDayCount = count($dayRows);
        return new CollaboratorEvaluation(
            userId: $user->getId() ?? 0,
            displayName: $user->getDisplayName(),
            roleLabel: $user->getRole()->label(),
            active: $user->isActive(),
            periodFrom: $criteria->periodFrom,
            periodTo: $criteria->periodBefore->modify('-1 day'),
            totalMinutes: $totalMinutes,
            billableMinutes: $billableMinutes,
            entryCount: $entryCount,
            projectCount: count($projectIds),
            workedDayCount: $workedDayCount,
            averageMinutesPerWorkedDay: $workedDayCount > 0 ? (int) round($totalMinutes / $workedDayCount) : 0,
            days: $dayRows,
        );
    }

    private function nullableText(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
