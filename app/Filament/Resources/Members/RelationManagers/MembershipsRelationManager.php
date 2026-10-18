<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Actions\Memberships\AddMembership;
use App\Actions\Memberships\CancelPendingMembership;
use App\Actions\Memberships\UpdateMembership;
use App\Actions\Payments\RegisterMembershipPayment;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Filament\Actions\CardActions;
use App\Filament\Actions\PaymentActions;
use App\Filament\Forms\MoneyInput;
use App\Models\Membership;
use App\Services\Fees;
use App\Support\DutchDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Lidmaatschappen';

    protected static ?string $modelLabel = 'lidmaatschap';

    protected static ?string $pluralModelLabel = 'lidmaatschappen';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                DatePicker::make('starts_at')
                    ->label('Ingangsdatum')
                    ->required()
                    ->default(fn (): string => $this->nextPeriodStart()->toDateString())
                    ->live()
                    ->rule(fn (?Membership $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                        $exists = $this->getOwnerRecord()->memberships()
                            ->whereDate('starts_at', $value)
                            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                            ->exists();

                        if ($exists) {
                            $fail('Er is al een lidmaatschap met deze ingangsdatum.');
                        }
                    }),
                DatePicker::make('ends_at')
                    ->label('Einddatum')
                    ->required()
                    ->default(fn (): string => $this->nextPeriodStart()->endOfYear()->toDateString())
                    ->afterOrEqual('starts_at'),
                MoneyInput::make('amount')
                    ->label('Contributie')
                    ->default(fn (): string => Money::toInput(app(Fees::class)->annualFeeCents()))
                    ->required()
                    ->helperText('Er wordt een openstaande betaling aangemaakt. Het lidmaatschap wordt actief zodra die als betaald is gemarkeerd.')
                    ->visibleOn('create'),
                Select::make('status')
                    ->label('Status')
                    ->options(fn (?Membership $record): array => collect($record ? UpdateMembership::allowedStatuses($record) : [])
                        ->mapWithKeys(fn (MembershipStatus $status) => [$status->value => $status->getLabel()])
                        ->all())
                    ->disabled(fn (?Membership $record): bool => $record?->status === MembershipStatus::Pending)
                    ->helperText(fn (?Membership $record): string => $record?->status === MembershipStatus::Pending
                        ? 'Wordt actief via "Markeer als betaald", zodat de betaling, bevestiging en kaart worden verwerkt.'
                        : 'Een lidmaatschap kan niet met de hand op actief worden gezet.')
                    ->required()
                    ->native(false)
                    ->visibleOn('edit'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('payment'))
            ->recordTitle(fn (Membership $record): string => 'lidmaatschap '.$record->starts_at->format('d-m-Y').' t/m '.$record->ends_at->format('d-m-Y'))
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Ingangsdatum')
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Geldig t/m')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('payment.amount_cents')
                    ->label('Betaling')
                    ->formatStateUsing(fn (Membership $record): string => Money::format($record->payment->amount_cents).' · '.$record->payment->status->getLabel())
                    ->placeholder('—'),
                TextColumn::make('card_sent_at')
                    ->label('Kaart verstuurd')
                    ->dateTime()
                    ->placeholder('Nog niet'),
            ])
            ->defaultSort('starts_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Lidmaatschap toevoegen')
                    ->using(function (array $data): Model {
                        try {
                            return app(AddMembership::class)->handle(
                                $this->getOwnerRecord(),
                                CarbonImmutable::parse($data['starts_at']),
                                CarbonImmutable::parse($data['ends_at']),
                                (int) Money::parse($data['amount']),
                            );
                        } catch (InvalidArgumentException $e) {
                            throw ValidationException::withMessages(['mountedActions.0.data.amount' => $e->getMessage()]);
                        }
                    }),
            ])
            ->recordActions([
                $this->markPaidAction(),
                ActionGroup::make([
                    ...CardActions::all(),
                    EditAction::make()
                        ->using(function (Membership $record, array $data): Model {
                            try {
                                return app(UpdateMembership::class)->handle(
                                    $record,
                                    CarbonImmutable::parse($data['starts_at']),
                                    CarbonImmutable::parse($data['ends_at']),
                                    MembershipStatus::from($data['status'] ?? $record->status->value),
                                );
                            } catch (InvalidArgumentException $e) {
                                throw ValidationException::withMessages(['mountedActions.0.data.status' => $e->getMessage()]);
                            }
                        }),
                    $this->cancelPendingAction(),
                ]),
            ])
            ->emptyStateHeading('Nog geen lidmaatschappen')
            ->emptyStateDescription('Voeg een lidmaatschap toe om een kaart te kunnen maken.');
    }

    private function markPaidAction(): Action
    {
        return Action::make('markPaid')
            ->label('Markeer als betaald')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading('Betaling registreren')
            ->modalDescription(fn (Membership $record): string => $record->payment?->isOpen()
                ? "{$record->payment->description}: ".Money::format($record->payment->amount_cents).'. Het lidmaatschap wordt actief.'
                : 'Bij dit lidmaatschap hoort nog geen betaling; die wordt nu vastgelegd. Het lidmaatschap wordt actief.')
            ->modalSubmitActionLabel('Markeer als betaald')
            ->schema(fn (Membership $record): array => [
                ...($record->payment?->isOpen() ? [] : [
                    MoneyInput::make('amount')
                        ->label('Bedrag')
                        ->default(Money::toInput(app(Fees::class)->annualFeeCents()))
                        ->required(),
                ]),
                ...PaymentActions::paidForm(),
            ])
            ->authorize('markPaid')
            ->action(function (Membership $record, array $data): void {
                $note = app(RegisterMembershipPayment::class)->handle(
                    $record,
                    PaymentMethod::from($data['method']),
                    DutchDate::parse($data['paid_at']),
                    $data['reference'] ?? null,
                    (bool) $data['send_card'],
                    (bool) $data['send_confirmation'],
                    isset($data['amount']) ? Money::parse($data['amount']) : null,
                );

                Notification::make()
                    ->title('Betaling geregistreerd')
                    ->body($note ? "Geen kaart gemaild: {$note}" : 'De lidmaatschapskaart wordt gemaild.')
                    ->success()
                    ->send();
            });
    }

    private function cancelPendingAction(): Action
    {
        return Action::make('cancelPending')
            ->label('Annuleren')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Lidmaatschap annuleren')
            ->modalDescription('Het lidmaatschap en de bijbehorende openstaande betaling worden geannuleerd. Hoort de betaling bij meerdere periodes (instap in december), dan vervallen die allemaal.')
            ->modalSubmitActionLabel('Annuleren')
            ->schema([
                TextInput::make('reason')->label('Reden (optioneel)')->maxLength(255),
            ])
            ->authorize('cancelPending')
            ->action(function (Membership $record, array $data): void {
                app(CancelPendingMembership::class)->handle($record, $data['reason'] ?? null);

                Notification::make()->title('Lidmaatschap geannuleerd')->success()->send();
            });
    }

    /**
     * New periods default to the calendar year after the latest one (or this year when there is none).
     */
    private function nextPeriodStart(): CarbonImmutable
    {
        $latestEnd = $this->getOwnerRecord()->memberships()->max('ends_at');

        return $latestEnd
            ? CarbonImmutable::parse($latestEnd)->addDay()->startOfDay()
            : CarbonImmutable::today()->startOfYear();
    }
}
