<?php

declare(strict_types=1);

namespace App\Query;

use App\Enum\ActivityStatus;

final class CollaboratorReportPerson
{
    /** @var list<CollaboratorReportActivity> */
    public array $openActivities = [];
    /** @var list<CollaboratorReportActivity> */
    public array $completedActivities = [];
    /** @var list<CollaboratorReportEntry> */
    public array $entries = [];
    /** @var list<CollaboratorReportEntry> */
    public array $timers = [];
    public ?CollaboratorReportEntry $lastEntry = null;
    public int $minutes = 0;
    public int $billableMinutes = 0;
    public int $costCents = 0;

    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly bool $active,
    ) {
    }

    public function overdueCount(): int
    {
        return count(array_filter($this->openActivities, static fn (CollaboratorReportActivity $a): bool => $a->overdue));
    }

    public function waitingCount(): int
    {
        return count(array_filter($this->openActivities, static fn (CollaboratorReportActivity $a): bool => ActivityStatus::Waiting === $a->status));
    }

    public function missingEstimateCount(): int
    {
        return count(array_filter($this->openActivities, static fn (CollaboratorReportActivity $a): bool => $a->missingEstimate()));
    }

    public function oldTimerCount(): int
    {
        $today = new \DateTimeImmutable('today');

        return count(array_filter($this->timers, static fn (CollaboratorReportEntry $entry): bool => $entry->startedAt < $today));
    }

    public function missingDescriptionCount(): int
    {
        return count(array_filter($this->entries, static fn (CollaboratorReportEntry $entry): bool => null === $entry->description));
    }

    public function attentionCount(): int
    {
        return $this->overdueCount() + $this->oldTimerCount() + $this->waitingCount() + $this->missingEstimateCount();
    }

    /** @return list<array{activityId: int, activityTitle: string, projectId: int, projectCode: string, projectName: string, minutes: int, billableMinutes: int, costCents: int}> */
    public function workGroups(): array
    {
        $groups = [];
        foreach ($this->entries as $entry) {
            $key = $entry->activityId;
            $groups[$key] ??= [
                'activityId' => $entry->activityId, 'activityTitle' => $entry->activityTitle,
                'projectId' => $entry->projectId, 'projectCode' => $entry->projectCode, 'projectName' => $entry->projectName,
                'minutes' => 0, 'billableMinutes' => 0, 'costCents' => 0,
            ];
            $groups[$key]['minutes'] += $entry->minutes;
            $groups[$key]['billableMinutes'] += $entry->billable ? $entry->minutes : 0;
            $groups[$key]['costCents'] += $entry->costCents;
        }
        $rows = array_values($groups);
        usort($rows, static fn (array $a, array $b): int => $b['minutes'] <=> $a['minutes']);

        return $rows;
    }

    /** @return list<array{date: string, minutes: int, entries: list<CollaboratorReportEntry>}> */
    public function days(): array
    {
        $days = [];
        foreach ($this->entries as $entry) {
            $key = $entry->startedAt->format('Y-m-d');
            $days[$key] ??= ['date' => $key, 'minutes' => 0, 'entries' => []];
            $days[$key]['minutes'] += $entry->minutes;
            $days[$key]['entries'][] = $entry;
        }
        krsort($days);

        return array_values($days);
    }
}
