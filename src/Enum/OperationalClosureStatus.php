<?php

declare(strict_types=1);

namespace App\Enum;

enum OperationalClosureStatus: string
{
    case Open = 'open';
    case Ready = 'ready';
    case Closed = 'closed';
    case Inconsistent = 'inconsistent';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Lavoro aperto',
            self::Ready => 'Pronta da chiudere',
            self::Closed => 'Chiusa operativamente',
            self::Inconsistent => 'Stato incoerente',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-lt',
            self::Ready => 'bg-azure-lt',
            self::Closed => 'bg-green-lt',
            self::Inconsistent => 'bg-red-lt',
        };
    }

    public function isClosed(): bool
    {
        return self::Closed === $this;
    }
}
