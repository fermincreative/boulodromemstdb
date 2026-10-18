<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasColor, HasLabel
{
    case Admin = 'admin';
    case Treasurer = 'treasurer';
    case Viewer = 'viewer';

    public function getLabel(): string
    {
        return match ($this) {
            self::Admin => 'Beheerder',
            self::Treasurer => 'Penningmeester',
            self::Viewer => 'Alleen lezen',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Treasurer => 'warning',
            self::Viewer => 'gray',
        };
    }
}
