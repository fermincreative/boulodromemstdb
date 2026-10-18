<?php

namespace App\Filament\Resources\Members\Actions;

use App\Actions\Mail\QueueMemberMail;
use App\Enums\PaymentStatus;
use App\Filament\Forms\MailFields;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Models\Payment;
use App\Services\MailTemplates;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * "Aanmeldingsmail versturen": welcome + payment details for the member's open payment, editable before sending.
 * Opens automatically right after a new member is created.
 */
class SendWelcomeMailAction
{
    public static function make(): Action
    {
        return Action::make('sendWelcome')
            ->label('Aanmeldingsmail versturen')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->modalHeading('Aanmeldingsmail versturen')
            ->modalDescription(fn (Member $record): string => "Aan {$record->full_name} <{$record->email}>, met de betaalgegevens voor ".self::openPayment($record)?->description.'.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Versturen')
            ->modalCancelActionLabel('Niet versturen')
            ->fillForm(fn (Member $record): array => app(MailTemplates::class)->renderWelcome(self::openPayment($record)))
            ->schema(MailFields::make())
            ->visible(fn (Member $record): bool => self::openPayment($record) !== null && filled($record->email))
            ->authorize(fn (Member $record): bool => (bool) auth()->user()?->can('update', $record) || (bool) auth()->user()?->canManagePayments())
            ->action(function (Member $record, array $data): void {
                app(QueueMemberMail::class)->handle($record, MailTemplate::WELCOME, $data['subject'], $data['body']);

                Notification::make()->title('Aanmeldingsmail wordt verstuurd')->body("Naar {$record->email}.")->success()->send();
            });
    }

    public static function openPayment(Member $member): ?Payment
    {
        return $member->payments()->where('status', PaymentStatus::Open)->latest('id')->first();
    }
}
