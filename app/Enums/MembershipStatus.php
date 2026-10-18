<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MembershipStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Wacht op betaling',
            self::Active => 'Actief',
            self::Expired => 'Verlopen',
            self::Cancelled => 'Geannuleerd',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Expired => 'gray',
            self::Cancelled => 'danger',
        };
    }
}
