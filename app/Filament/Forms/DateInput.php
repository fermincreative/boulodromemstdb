<?php

namespace App\Filament\Forms;

use App\Support\DutchDate;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Livewire\Component as Livewire;

/**
 * A date field you type in ("10-09-1992", "10/9/92", "10091992"), with a calendar button as a secondary option.
 * The model receives Y-m-d; the field always shows dd-mm-jjjj.
 */
class DateInput
{
    public static function make(string $name, ?CarbonImmutable $min = null, ?CarbonImmutable $max = null): TextInput
    {
        return TextInput::make($name)
            ->placeholder('dd-mm-jjjj')
            ->maxLength(20)
            ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                $date = $state instanceof \DateTimeInterface
                    ? CarbonImmutable::instance($state)
                    : DutchDate::parse(is_string($state) ? $state : null);

                if ($date) {
                    $component->state(DutchDate::format($date));
                }
            })
            // Tidy up what was typed ("10/9/92" → "10-09-1992") and validate as soon as the field is left.
            ->live(onBlur: true)
            ->afterStateUpdated(function (TextInput $component, ?string $state, Livewire $livewire): void {
                if ($date = DutchDate::parse($state)) {
                    $component->state(DutchDate::format($date));
                }

                $livewire->validateOnly($component->getStatePath());
            })
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($min, $max): void {
                if (blank($value)) {
                    return;
                }

                $date = DutchDate::parse((string) $value);

                if (! $date) {
                    $fail('Vul een geldige datum in, bijvoorbeeld 10-09-1992.');
                } elseif ($min && $date->lt($min)) {
                    $fail('De datum mag niet voor '.$min->format('d-m-Y').' liggen.');
                } elseif ($max && $date->gt($max)) {
                    $fail('De datum mag niet na '.$max->format('d-m-Y').' liggen.');
                }
            })
            ->dehydrateStateUsing(fn (?string $state): ?string => DutchDate::parse($state)?->toDateString())
            ->suffixAction(
                Action::make("{$name}Picker")
                    ->label('Kies een datum')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->modalHeading('Kies een datum')
                    ->modalWidth(Width::Small)
                    ->modalSubmitActionLabel('Gebruiken')
                    ->fillForm(fn (?string $state): array => ['date' => DutchDate::parse($state)?->toDateString()])
                    ->schema([
                        DatePicker::make('date')
                            ->hiddenLabel()
                            ->minDate($min)
                            ->maxDate($max)
                            ->required(),
                    ])
                    // Set the field and run its "updated" hooks, exactly as if the date had been typed.
                    ->action(function (array $data, TextInput $component): void {
                        $component->state(DutchDate::format(DutchDate::parse($data['date'])));
                        $component->callAfterStateUpdated();
                    }),
            );
    }
}
