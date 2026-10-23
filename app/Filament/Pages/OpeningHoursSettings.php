<?php

namespace App\Filament\Pages;

use App\Actions\Schedule\SaveOpeningHours;
use App\Enums\SchedulePeriod;
use App\Filament\Forms\TimeInput;
use App\Services\OpeningHours;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Admins set the standard times of Middag and Avond in the Rooster.
 *
 * @property-read Schema $form
 */
class OpeningHoursSettings extends Page
{
    protected static ?string $slug = 'openingstijden';

    protected static ?string $title = 'Openingstijden';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 83;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        [$afternoonStart, $afternoonEnd] = OpeningHours::times(SchedulePeriod::Afternoon);
        [$eveningStart, $eveningEnd] = OpeningHours::times(SchedulePeriod::Evening);

        $this->form->fill([
            'afternoon_start' => $afternoonStart,
            'afternoon_end' => $afternoonEnd,
            'evening_start' => $eveningStart,
            'evening_end' => $eveningEnd,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $times = fn (string $period): array => [
            TimeInput::make("{$period}_start")->label('Van')->required(),
            TimeInput::make("{$period}_end")->label('Tot')->required(),
        ];

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Middag')
                    ->columns(['default' => 2, 'md' => 4])
                    ->schema($times('afternoon')),
                Section::make('Avond')
                    ->columns(['default' => 2, 'md' => 4])
                    ->schema($times('evening')),
            ]);
    }

    public function getSubheading(): ?string
    {
        return 'De standaardtijden van de dagdelen in het Rooster. Een wijziging geldt voor dagdelen die u daarna opent; '
            .'dagdelen die al open staan, houden hun tijden. Extra dagdelen met eigen tijden voegt u per dag toe in het Rooster.';
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            app(SaveOpeningHours::class)->handle($data['afternoon_start'], $data['afternoon_end'], $data['evening_start'], $data['evening_end']);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages(collect($e->errors())->mapWithKeys(fn ($messages, $key) => ["data.{$key}" => $messages])->all());
        }

        $this->mount();

        Notification::make()->title('Openingstijden opgeslagen')->success()->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Opslaan')->submit('save'),
                    ]),
                ]),
        ]);
    }
}
