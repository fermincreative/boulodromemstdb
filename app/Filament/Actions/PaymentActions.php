<?php

namespace App\Filament\Actions;

use App\Actions\Payments\CancelPayment;
use App\Actions\Payments\MarkPaymentPaid;
use App\Enums\PaymentMethod;
use App\Filament\Forms\DateInput;
use App\Models\Payment;
use App\Support\DutchDate;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * "Markeer als betaald" (single and bulk) and "Annuleren" for payments.
 */
class PaymentActions
{
    public static function markPaid(): Action
    {
        return Action::make('markPaid')
            ->label('Markeer als betaald')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading(fn (Payment $record): string => 'Betaling van '.Money::format($record->amount_cents).' ontvangen')
            ->modalDescription(fn (Payment $record): string => "{$record->member->full_name} — {$record->description}. Het lidmaatschap wordt actief.")
            ->modalSubmitActionLabel('Markeer als betaald')
            ->schema(self::paidForm())
            ->authorize('markPaid')
            ->action(function (Payment $record, array $data): void {
                $note = app(MarkPaymentPaid::class)->handle(
                    $record,
                    PaymentMethod::from($data['method']),
                    DutchDate::parse($data['paid_at']),
                    $data['reference'] ?? null,
                    (bool) $data['send_card'],
                    (bool) $data['send_confirmation'],
                );

                Notification::make()
                    ->title('Betaling geregistreerd')
                    ->body($note ? "Geen kaart gemaild: {$note}" : 'De lidmaatschapskaart wordt gemaild.')
                    ->success()
                    ->send();
            });
    }

    public static function bulkMarkPaid(): BulkAction
    {
        return BulkAction::make('markPaidBulk')
            ->label('Markeer als betaald')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->modalHeading('Geselecteerde betalingen als betaald markeren')
            ->modalDescription('Alleen openstaande betalingen worden verwerkt. De lidmaatschappen worden actief.')
            ->schema(self::paidForm(withReference: false))
            ->authorize('markAnyPaid', Payment::class)
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data): void {
                $open = $records->filter(fn (Payment $payment) => $payment->isOpen());
                $withoutCard = 0;

                foreach ($open as $payment) {
                    $note = app(MarkPaymentPaid::class)->handle(
                        $payment,
                        PaymentMethod::from($data['method']),
                        DutchDate::parse($data['paid_at']),
                        sendCard: (bool) $data['send_card'],
                        sendConfirmation: (bool) $data['send_confirmation'],
                    );
                    $withoutCard += $note ? 1 : 0;
                }

                $skipped = $records->count() - $open->count();

                Notification::make()
                    ->title($open->count().' '.($open->count() === 1 ? 'betaling' : 'betalingen').' geregistreerd')
                    ->body(collect([
                        $skipped ? "{$skipped} overgeslagen (niet openstaand)." : null,
                        $withoutCard ? "{$withoutCard} zonder kaart per e-mail (bijv. geen e-mailadres)." : null,
                    ])->filter()->implode(' ') ?: null)
                    ->success()
                    ->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancelPayment')
            ->label('Annuleren')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Betaling annuleren')
            ->modalDescription('Het lidmaatschap waar deze betaling voor was, wordt ook geannuleerd. Gebruik dit bijvoorbeeld als iemand toch geen lid wordt.')
            ->modalSubmitActionLabel('Annuleren')
            ->schema([
                TextInput::make('reason')->label('Reden (optioneel)')->maxLength(255),
            ])
            ->authorize('cancel')
            ->action(function (Payment $record, array $data): void {
                app(CancelPayment::class)->handle($record, $data['reason'] ?? null);

                Notification::make()->title('Betaling geannuleerd')->success()->send();
            });
    }

    /**
     * @return array<int, mixed>
     */
    public static function paidForm(bool $withReference = true): array
    {
        return array_values(array_filter([
            Radio::make('method')
                ->label('Betaalwijze')
                ->options(PaymentMethod::manualOptions())
                ->default(PaymentMethod::BankTransfer->value)
                ->inline()
                ->required(),
            DateInput::make('paid_at', max: CarbonImmutable::today())
                ->label('Betaald op')
                ->default(fn (): string => today()->format('d-m-Y'))
                ->required(),
            $withReference ? TextInput::make('reference')->label('Kenmerk (optioneel)')->maxLength(255) : null,
            Toggle::make('send_confirmation')
                ->label('Lid informeren (betalingsbevestiging mailen)')
                ->helperText('Met de datum waarop de betaling is verwerkt. Leden zonder e-mailadres worden overgeslagen.')
                ->default(true),
            Toggle::make('send_card')
                ->label('Lidmaatschapskaart direct mailen')
                ->default(true),
        ]));
    }
}
