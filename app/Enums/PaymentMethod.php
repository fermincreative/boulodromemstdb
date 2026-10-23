<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Ideal = 'ideal';
    case Bancontact = 'bancontact';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cash => 'Contant',
            self::BankTransfer => 'Overboeking',
            self::Ideal => 'iDEAL',
            self::Bancontact => 'Bancontact',
        };
    }

    /**
     * The method a Mollie payment was paid with (Mollie method id, e.g. "bancontact").
     */
    public static function fromMollie(?string $method): self
    {
        return self::tryFrom((string) $method) ?? self::Ideal;
    }

    /**
     * Paid online through Mollie.
     */
    public function isOnline(): bool
    {
        return in_array($this, [self::Ideal, self::Bancontact], true);
    }

    /**
     * Methods a person can register by hand (online payments come in through Mollie).
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
