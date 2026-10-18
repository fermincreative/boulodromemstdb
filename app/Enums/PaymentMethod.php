<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Ideal = 'ideal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Contant',
            self::BankTransfer => 'Overboeking',
            self::Ideal => 'iDEAL',
        };
    }

    /**
     * Methods a person can register by hand (iDEAL comes later, via Mollie).
     *
     * @return array<string, string>
     */
    public static function manualOptions(): array
    {
        return [
            self::BankTransfer->value => self::BankTransfer->getLabel(),
            self::Cash->value => self::Cash->getLabel(),
        ];
    }
}
