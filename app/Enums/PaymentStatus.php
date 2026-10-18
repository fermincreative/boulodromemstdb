<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PaymentStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Openstaand',
            self::Paid => 'Betaald',
            self::Cancelled => 'Geannuleerd',
            self::Refunded => 'Terugbetaald',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'gray',
            self::Refunded => 'danger',
        };
    }
}
