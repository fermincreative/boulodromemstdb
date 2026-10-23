<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ScheduleSlotStatus: string implements HasColor, HasLabel
{
    case Closed = 'closed';
    case Open = 'open';
    case Event = 'event';

    public function getLabel(): string
    {
        return match ($this) {
            self::Closed => 'Gesloten',
            self::Open => 'Open',
            self::Event => 'Evenement',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Closed => 'gray',
            self::Open => 'success',
            self::Event => 'info',
        };
    }
}
