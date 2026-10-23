<?php

namespace App\Filament\Pages;

use App\Actions\Memberships\RenewMembership;
use App\Actions\Rates\SaveBankDetails;
use App\Actions\Rates\SaveRates;
use App\Actions\Rates\SaveRenewalWindow;
use App\Filament\Forms\MoneyInput;
use App\Models\JoiningRate;
use App\Models\Setting;
use App\Services\Fees;
use App\Support\Iban;
use App\Support\Money;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
 * Admins edit the annual fee and the joining rates for new members.
 *
 * @property-read Schema $form
 */
class Rates extends Page
{
    protected static ?string $slug = 'tarieven';

    protected static ?string $title = 'Tarieven';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    protected static string|UnitEnum|null $navigationGroup = 'Beheer';

    protected static ?int $navigationSort = 84;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill([
            'annual_fee' => Money::toInput(app(Fees::class)->annualFeeCents()),
            'renewal_window_days' => RenewMembership::windowDays(),
            'bank_iban' => Setting::get(Setting::BANK_IBAN),
            'bank_account_holder' => Setting::get(Setting::BANK_ACCOUNT_HOLDER),
            'joining_rates' => JoiningRate::orderBy('starts_on')->get()->map(function (JoiningRate $rate) {
                [$month, $day] = array_map('intval', explode('-', $rate->starts_on));

                return [
                    'day' => $day,
                    'month' => $month,
                    'amount' => Money::toInput($rate->amount_cents),
                    'includes_next_year' => $rate->includes_next_year,
                ];
            })->all(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $months = collect(range(1, 12))->mapWithKeys(fn (int $m) => [$m => now()->setDate(2000, $m, 1)->translatedFormat('F')])->all();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Jaarcontributie')
                    ->description('Voor verlengingen: het lidmaatschap voor een heel kalenderjaar.')
                    ->schema([
                        MoneyInput::make('annual_fee')
                            ->label('Bedrag per jaar')
                            ->required()
                            ->maxWidth('xs'),
                    ]),
                Section::make('Verlengingen')
                    ->description('Een lid staat onder Verlengingen → "Te verlengen" zodra het lidmaatschap binnen dit aantal dagen afloopt. Eerder staat het lid onder "Nog niet aan de beurt" (en kunt u het met de hand naar "Te verlengen" zetten).')
                    ->schema([
                        TextInput::make('renewal_window_days')
                            ->label('Dagen vóór de einddatum')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->suffix('dagen')
                            ->required()
                            ->maxWidth('xs'),
                    ]),
                Section::make('Bankrekening')
                    ->description('Voor overboekingen; staat in de aanmeldingsmail ({iban} en {rekeninghouder}).')
                    ->columns(['default' => 1, 'md' => 2])
                    ->schema([
                        TextInput::make('bank_iban')
                            ->label('IBAN')
                            ->required()
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (! Iban::isValid((string) $value)) {
                                    $fail('Dit is geen geldig IBAN. Controleer het rekeningnummer.');
                                }
                            }),
                        TextInput::make('bank_account_holder')
                            ->label('Ten name van')
                            ->required()
                            ->maxLength(255),
                    ]),
                Section::make('Instaptarieven nieuwe leden')
                    ->description('Een nieuw lid betaalt het tarief dat geldt op de dag van inschrijving, voor de rest van het kalenderjaar. Wijzigingen gelden voor nieuwe betalingen; bestaande betalingen houden hun bedrag.')
                    ->schema([
                        Repeater::make('joining_rates')
                            ->hiddenLabel()
                            ->columns(['default' => 1, 'md' => 12])
                            ->addActionLabel('Instaptarief toevoegen')
                            ->reorderable(false)
                            ->minItems(1)
                            ->schema([
                                Select::make('day')
                                    ->label('Vanaf dag')
                                    ->options(array_combine(range(1, 31), range(1, 31)))
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(['md' => 2]),
                                Select::make('month')
                                    ->label('Maand')
                                    ->options($months)
                                    ->required()
                                    ->columnSpan(['md' => 3]),
                                MoneyInput::make('amount')
                                    ->label('Bedrag')
                                    ->required()
                                    ->columnSpan(['md' => 3]),
                                Toggle::make('includes_next_year')
                                    ->label('Plus jaarcontributie volgend jaar')
                                    ->helperText('In één betaling, met lidmaatschap tot en met 31-12 van volgend jaar.')
                                    ->inline(false)
                                    ->columnSpan(['md' => 4]),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $rates = collect($data['joining_rates'] ?? [])
            ->map(fn (array $row) => [
                'starts_on' => sprintf('%02d-%02d', $row['month'], $row['day']),
                'amount_cents' => (int) Money::parse($row['amount']),
                'includes_next_year' => (bool) ($row['includes_next_year'] ?? false),
            ])
            ->sortBy('starts_on')
            ->values()
            ->all();

        app(SaveBankDetails::class)->handle($data['bank_iban'], $data['bank_account_holder']);
        app(SaveRenewalWindow::class)->handle((int) $data['renewal_window_days']);

        try {
            app(SaveRates::class)->handle((int) Money::parse($data['annual_fee']), $rates);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            Notification::make()->title('Tarieven niet opgeslagen')->body(collect($errors)->flatten()->implode(' '))->danger()->send();

            throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($messages, $key) => [
                $key === 'annual_fee' ? 'data.annual_fee' : 'data.joining_rates' => $messages,
            ])->all());
        }

        $this->mount();

        Notification::make()->title('Tarieven opgeslagen')->success()->send();
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
