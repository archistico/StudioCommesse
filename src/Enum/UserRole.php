<?php

declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case Partner = 'ROLE_PARTNER';
    case Collaborator = 'ROLE_COLLABORATOR';

    public function label(): string
    {
        return match ($this) {
            self::Partner => 'Socio',
            self::Collaborator => 'Collaboratore',
        };
    }
}
