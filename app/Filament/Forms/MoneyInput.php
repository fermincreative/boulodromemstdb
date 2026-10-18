<?php

namespace App\Filament\Forms;

use App\Support\Money;
use Closure;
use Filament\Forms\Components\TextInput;

/**
 * An amount in euros, typed as "67,50" or "67.50". The state is a string; read it with Money::parse().
 */
class MoneyInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->prefix('€')
            ->placeholder('0,00')
            ->inputMode('decimal')
            ->maxLength(12)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && Money::parse((string) $value) === null) {
                    $fail('Vul een bedrag in, bijvoorbeeld 67,50.');
                }
            })
            ->live(onBlur: true)
            ->afterStateUpdated(function (TextInput $component, ?string $state): void {
                if (($cents = Money::parse($state)) !== null) {
                    $component->state(Money::toInput($cents));
                }
            });
    }
}
