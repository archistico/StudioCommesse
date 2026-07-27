<?php

declare(strict_types=1);

namespace App\Query;

use App\Entity\TimeEntry;

final readonly class TimeEntryPage
{
    /**
     * @param list<TimeEntry> $items
     */
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $page,
        public int $perPage,
        public int $totalPages,
    ) {
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->totalPages;
    }

    public function getFirstItemNumber(): int
    {
        return 0 === $this->totalItems ? 0 : (($this->page - 1) * $this->perPage) + 1;
    }

    public function getLastItemNumber(): int
    {
        return min($this->page * $this->perPage, $this->totalItems);
    }
}
