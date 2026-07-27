<?php

declare(strict_types=1);

namespace App\Enum;

enum OverallClosureStatus: string
{
    case Open = 'open';
    case WorkOpen = 'work_open';
    case ToCollect = 'to_collect';
    case Closed = 'closed';
    case Attention = 'attention';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aperta',
            self::WorkOpen => 'Lavoro ancora aperto',
            self::ToCollect => 'Lavoro chiuso, da incassare',
            self::Closed => 'Chiusa completamente',
            self::Attention => 'Richiede attenzione',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'bg-blue-lt',
            self::WorkOpen => 'bg-purple-lt',
            self::ToCollect => 'bg-yellow-lt',
            self::Closed => 'bg-green-lt',
            self::Attention => 'bg-red-lt',
        };
    }
}
