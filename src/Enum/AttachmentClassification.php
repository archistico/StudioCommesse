<?php

declare(strict_types=1);

namespace App\Enum;

enum AttachmentClassification: string
{
    case Technical = 'technical';
    case Contractual = 'contractual';
    case Administrative = 'administrative';
    case Communication = 'communication';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Tecnico',
            self::Contractual => 'Contrattuale',
            self::Administrative => 'Amministrativo',
            self::Communication => 'Comunicazione',
            self::Other => 'Altro',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Technical => 'bg-blue-lt',
            self::Contractual => 'bg-purple-lt',
            self::Administrative => 'bg-yellow-lt',
            self::Communication => 'bg-cyan-lt',
            self::Other => 'bg-secondary-lt',
        };
    }
}
