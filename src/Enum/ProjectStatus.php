<?php

declare(strict_types=1);

namespace App\Enum;

enum ProjectStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Waiting = 'waiting';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Da iniziare',
            self::InProgress => 'In corso',
            self::Waiting => 'In attesa',
            self::Completed => 'Completata',
            self::Cancelled => 'Annullata',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NotStarted => 'bg-secondary-lt',
            self::InProgress => 'bg-blue-lt',
            self::Waiting => 'bg-yellow-lt',
            self::Completed => 'bg-green-lt',
            self::Cancelled => 'bg-red-lt',
        };
    }

    public function isClosed(): bool
    {
        return self::Completed === $this || self::Cancelled === $this;
    }
}
