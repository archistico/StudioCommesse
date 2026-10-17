<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\ActivityStatus;
use App\Query\CollaboratorReportActivity;
use App\Query\CollaboratorReportCriteria;
use App\Query\CollaboratorReportEntry;
use App\Query\CollaboratorReportPerson;
use App\Repository\CollaboratorReportRepository;
use DateTimeImmutable;

final readonly class CollaboratorReportService
{
    public function __construct(private CollaboratorReportRepository $repository)
    {
    }

    /** @return list<CollaboratorReportPerson> */
    public function build(CollaboratorReportCriteria $criteria): array
    {
        $people = [];
        foreach ($this->repository->people($criteria) as $row) {
            $id = (int) $row['id'];
            $people[$id] = new CollaboratorReportPerson($id, (string) $row['display_name'], (bool) $row['active']);
        }
        $now = new DateTimeImmutable();
        foreach ($this->repository->activities($criteria) as $row) {
            $status = ActivityStatus::from((string) $row['status']);
            $due = $this->date($row['due_at']);
            $activity = new CollaboratorReportActivity(
                (int) $row['id'], (int) $row['user_id'], (int) $row['project_id'],
                (string) $row['project_code'], (string) $row['project_name'], (string) $row['title'],
                $status, (int) $row['progress_percent'], $due, $this->date($row['completed_at']),
                null === $row['initial_estimated_minutes'] ? null : (int) $row['initial_estimated_minutes'],
                null === $row['remaining_estimated_minutes'] ? null : (int) $row['remaining_estimated_minutes'],
                (int) $row['actual_minutes'], !$status->isClosed() && null !== $due && $due < $now,
            );
            if (ActivityStatus::Completed === $status) {
                $people[$activity->userId]->completedActivities[] = $activity;
            } else {
                $people[$activity->userId]->openActivities[] = $activity;
            }
        }
        foreach ($this->repository->entries($criteria) as $row) {
            $entry = $this->entry($row);
            $person = $people[$entry->userId];
            $person->entries[] = $entry;
            $person->minutes += $entry->minutes;
            $person->billableMinutes += $entry->billable ? $entry->minutes : 0;
            $person->costCents += $entry->costCents;
        }
        foreach ($this->repository->entries($criteria, current: true) as $row) {
            $entry = $this->entry($row);
            $person = $people[$entry->userId];
            if (null === $entry->endedAt) {
                $person->timers[] = $entry;
            }
            $person->lastEntry ??= $entry;
        }
        $rows = array_values(array_filter($people, static fn (CollaboratorReportPerson $person): bool =>
            $person->active || null !== $criteria->userId || [] !== $person->entries
            || [] !== $person->openActivities || [] !== $person->completedActivities || [] !== $person->timers
        ));
        usort($rows, static fn (CollaboratorReportPerson $a, CollaboratorReportPerson $b): int =>
            ($b->attentionCount() <=> $a->attentionCount()) ?: strnatcasecmp($a->name, $b->name)
        );

        return $rows;
    }

    /** @param array<string, mixed> $row */
    private function entry(array $row): CollaboratorReportEntry
    {
        $description = trim((string) ($row['description'] ?? ''));

        return new CollaboratorReportEntry(
            (int) $row['id'], (int) $row['user_id'], (int) $row['project_id'],
            (string) $row['project_code'], (string) $row['project_name'], null !== $row['archived_at'],
            (int) $row['activity_id'], (string) $row['activity_title'],
            new DateTimeImmutable((string) $row['started_at']), $this->date($row['ended_at']),
            '' === $description ? null : $description, (bool) $row['billable'],
            (int) ($row['minutes'] ?? 0), (int) $row['cost_snapshot_cents'],
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        return null === $value ? null : new DateTimeImmutable((string) $value);
    }
}
