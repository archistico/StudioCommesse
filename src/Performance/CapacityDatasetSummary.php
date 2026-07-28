<?php

declare(strict_types=1);

namespace App\Performance;

final readonly class CapacityDatasetSummary
{
    public function __construct(
        public CapacityProfile $profile,
        public int $users,
        public int $clients,
        public int $projects,
        public int $activities,
        public int $timeEntries,
        public int $expenses,
        public int $payments,
        public int $audits,
        public int $attachments,
    ) {
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'profile' => $this->profile->label(),
            'users' => $this->users,
            'clients' => $this->clients,
            'projects' => $this->projects,
            'activities' => $this->activities,
            'time_entries' => $this->timeEntries,
            'expenses' => $this->expenses,
            'payments' => $this->payments,
            'audits' => $this->audits,
            'attachments' => $this->attachments,
        ];
    }
}
