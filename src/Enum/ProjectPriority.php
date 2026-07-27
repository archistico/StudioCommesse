<?php

declare(strict_types=1);

namespace App\Enum;

enum ProjectPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Bassa',
            self::Normal => 'Normale',
            self::High => 'Alta',
            self::Urgent => 'Urgente',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Low => 'bg-secondary-lt',
            self::Normal => 'bg-azure-lt',
            self::High => 'bg-orange-lt',
            self::Urgent => 'bg-red-lt',
        };
    }
}
