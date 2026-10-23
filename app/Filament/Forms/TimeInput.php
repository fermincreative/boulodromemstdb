<?php

namespace App\Filament\Forms;

use App\Services\OpeningHours;
use Closure;
use Filament\Forms\Components\TextInput;
use Livewire\Component as Livewire;

/**
 * A time field in 24-hour notation that you type in ("13:00", "1330", "9.30", "9"); always shows and saves "HH:MM".
 * Browser time pickers follow the computer's settings and may show AM/PM, so this is a plain text field.
 */
class TimeInput
{
    public static function make(string $name): TextInput
    {
        return TextInput::make($name)
            ->placeholder('uu:mm')
            ->helperText('24-uurs notatie, bijv. 13:00.')
            ->maxLength(8)
            ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                if ($time = OpeningHours::normalize(is_string($state) ? $state : null)) {
                    $component->state($time);
                }
            })
            // Tidy up what was typed ("930" → "09:30") and validate as soon as the field is left.
            ->live(onBlur: true)
            ->afterStateUpdated(function (TextInput $component, ?string $state, Livewire $livewire): void {
                if ($time = OpeningHours::normalize($state)) {
                    $component->state($time);
                }

                $livewire->validateOnly($component->getStatePath());
            })
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (filled($value) && ! OpeningHours::normalize((string) $value)) {
                    $fail('Vul een tijd in als uu:mm (24 uur), bijvoorbeeld 13:00.');
                }
            })
            ->dehydrateStateUsing(fn (?string $state): ?string => OpeningHours::normalize($state));
    }
}
