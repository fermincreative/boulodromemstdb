<?php

namespace App\Filament\Resources\Members\Schemas;

use App\Actions\Members\CreateMember;
use App\Enums\PaymentMethod;
use App\Filament\Forms\DateInput;
use App\Services\Fees;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Livewire\Component as Livewire;

/**
 * One section per row (no side-by-side sections); fields sized to what they hold.
 * The member number is not on the form: it is assigned automatically and never changes.
 */
class MemberForm
{
    /** 12-column grid on wide screens, one field per row on phones. */
    private const GRID = ['default' => 1, 'md' => 12];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Persoonsgegevens')
                    ->columns(self::GRID)
                    ->schema([
                        TextInput::make('first_name')
                            ->label('Voornaam')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['md' => 5]),
                        TextInput::make('middle_name')
                            ->label('Tussenvoegsel')
                            ->maxLength(50)
                            ->columnSpan(['md' => 2]),
                        TextInput::make('last_name')
                            ->label('Achternaam')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(['md' => 5]),
                        DateInput::make('birthday', min: CarbonImmutable::create(1900), max: CarbonImmutable::today())
                            ->label('Verjaardag')
                            ->helperText('Typ de datum, bijv. 10-09-1992 of 10/9/1992.')
                            ->columnSpan(['md' => 4]),
                    ]),

                Section::make('Contact')
                    ->columns(self::GRID)
                    ->schema([
                        TextInput::make('email')
                            ->label('E-mailadres')
                            ->email()
                            ->rule('email:rfc,filter')
                            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                // "naam@gmail" or "naam@gmail.c" passes the RFC check but cannot receive mail.
                                if (filled($value) && ! preg_match('/@[^@\s]+\.[a-z]{2,}$/i', (string) $value)) {
                                    $fail('Dit e-mailadres lijkt onvolledig. Controleer het deel na de @ (bijv. naam@voorbeeld.nl).');
                                }
                            })
                            ->maxLength(255)
                            ->live(onBlur: true)
                            // Pasted addresses often carry spaces; clean up before validating and saving.
                            ->mutateStateForValidationUsing(fn (?string $state): ?string => filled($state) ? mb_strtolower(trim($state)) : null)
                            ->afterStateUpdated(function (TextInput $component, ?string $state, Livewire $livewire): void {
                                $component->state(filled($state) ? mb_strtolower(trim($state)) : null);
                                $livewire->validateOnly($component->getStatePath());
                            })
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtolower(trim($state)) : null)
                            ->helperText('Mag gedeeld worden met een ander lid (bijv. partners).')
                            ->columnSpanFull(),
                        TextInput::make('phone_mobile')
                            ->label('Mobiel')
                            ->tel()
                            ->maxLength(30)
                            ->columnSpan(['md' => 6]),
                        TextInput::make('phone_home')
                            ->label('Telefoon thuis')
                            ->tel()
                            ->maxLength(30)
                            ->columnSpan(['md' => 6]),
                    ]),

                Section::make('Adres')
                    ->columns(self::GRID)
                    ->schema([
                        TextInput::make('street')
                            ->label('Straat en huisnummer')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('postal_code')
                            ->label('Postcode')
                            ->maxLength(20)
                            ->columnSpan(['md' => 3]),
                        TextInput::make('city')
                            ->label('Woonplaats')
                            ->maxLength(255)
                            ->columnSpan(['md' => 5]),
                        TextInput::make('country')
                            ->label('Land')
                            ->default('Nederland')
                            ->datalist(['Nederland', 'België', 'Duitsland'])
                            ->maxLength(100)
                            ->columnSpan(['md' => 4]),
                    ]),

                Section::make('Vereniging')
                    ->columns(self::GRID)
                    ->schema([
                        Select::make('club_id')
                            ->label('Vereniging')
                            ->relationship('club', 'name')
                            ->placeholder('Geen')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('name')->label('Naam')->required()->unique('clubs', 'name'),
                            ])
                            ->columnSpan(['md' => 6]),
                        TextInput::make('contact_for')
                            ->label('Contactpersoon van')
                            ->maxLength(255)
                            ->columnSpan(['md' => 6]),
                        DateInput::make('joined_at', min: CarbonImmutable::create(1900), max: CarbonImmutable::today()->addYear())
                            ->label('Lid sinds')
                            ->default(fn (): string => today()->format('d-m-Y'))
                            ->columnSpan(['md' => 4]),
                        Textarea::make('notes')
                            ->label('Notities')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),

                // Only when creating: the first membership is created together with the member.
                Section::make('Eerste lidmaatschap')
                    ->description(function (): string {
                        [$startsAt, $endsAt] = CreateMember::firstPeriod();
                        $quote = app(Fees::class)->quoteForNewMember();
                        $nextYear = count($quote->periods) > 1 ? ' plus het hele jaar '.($endsAt->year + 1) : '';

                        return 'Loopt van vandaag ('.$startsAt->format('d-m-Y').') tot en met '.$endsAt->format('d-m-Y').$nextYear
                            .'. Contributie: '.$quote->summary().'.';
                    })
                    ->visibleOn('create')
                    ->columns(self::GRID)
                    ->schema([
                        Toggle::make('add_membership')
                            ->label('Direct een lidmaatschap toevoegen')
                            ->default(true)
                            ->live()
                            ->dehydrated(false)
                            ->columnSpan(['md' => 8]),
                        Select::make('payment')
                            ->label('Betaling')
                            ->options([
                                'open' => 'Nog te betalen',
                                PaymentMethod::BankTransfer->value => 'Al betaald (overboeking)',
                                PaymentMethod::Cash->value => 'Al betaald (contant)',
                            ])
                            ->default('open')
                            ->helperText('Bij "nog te betalen" kunt u na het opslaan de aanmeldingsmail met betaalgegevens bekijken en versturen. Bij "al betaald" wordt het lidmaatschap direct actief.')
                            ->live()
                            ->required(fn (Get $get): bool => (bool) $get('add_membership'))
                            ->visible(fn (Get $get): bool => (bool) $get('add_membership'))
                            ->native(false)
                            ->dehydrated(false)
                            ->columnSpan(['md' => 4]),
                        Toggle::make('notify_paid')
                            ->label('Betalingsbevestiging en lidmaatschapskaart mailen')
                            ->default(true)
                            ->visible(fn (Get $get): bool => (bool) $get('add_membership') && in_array($get('payment'), ['cash', 'bank_transfer'], true))
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
