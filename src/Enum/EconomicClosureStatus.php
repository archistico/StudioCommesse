<?php

declare(strict_types=1);

namespace App\Enum;

enum EconomicClosureStatus: string
{
    case Open = 'open';
    case Partial = 'partial';
    case Closed = 'closed';
    case Unconfigured = 'unconfigured';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Da incassare',
            self::Partial => 'Parzialmente incassata',
            self::Closed => 'Incassata',
            self::Unconfigured => 'Preventivo mancante',
            self::NotApplicable => 'Non applicabile',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'bg-yellow-lt',
            self::Partial => 'bg-orange-lt',
            self::Closed => 'bg-green-lt',
            self::Unconfigured => 'bg-red-lt',
            self::NotApplicable => 'bg-secondary-lt',
        };
    }

    public function isClosed(): bool
    {
        return self::Closed === $this || self::NotApplicable === $this;
    }
}
