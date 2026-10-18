<?php

namespace App\Filament\Resources\Members\Actions;

use App\Actions\Mail\QueueMemberMail;
use App\Actions\Members\CancelMember;
use App\Filament\Forms\DateInput;
use App\Filament\Forms\MailFields;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Services\MailTemplates;
use App\Support\DutchDate;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class CancelMemberAction
{
    public static function make(): Action
    {
        return Action::make('cancel')
            ->label('Opzeggen')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->modalHeading(fn (Member $record): string => "{$record->full_name} opzeggen")
            ->modalDescription('Het lidmaatschap loopt door tot de opzegdatum; standaard is dat het einde van de betaalde periode. Daarna geen verlenging en geen nieuwe kaart. Dit kan later worden teruggedraaid.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Opzeggen')
            ->fillForm(function (Member $record): array {
                $date = CancelMember::defaultDate($record);
                $mail = app(MailTemplates::class)->renderCancellation($record, $date);

                return [
                    'cancelled_at' => DutchDate::format($date),
                    'notify' => filled($record->email),
                    ...$mail,
                    'rendered_body' => $mail['body'],
                    'mail_date' => DutchDate::format($date),
                ];
            })
            ->schema([
                DateInput::make('cancelled_at')
                    ->label('Opgezegd per')
                    ->required()
                    ->helperText(fn (Member $record): string => 'Standaard: einde van de betaalde periode ('.DutchDate::format(CancelMember::defaultDate($record)).').')
                    // Keep the e-mail in step with the date: a fresh text when it was not edited, otherwise only the date is replaced.
                    ->afterStateUpdated(function (?string $state, Get $get, Set $set, Member $record): void {
                        $date = DutchDate::parse($state);

                        if (! $date) {
                            return;
                        }

                        $new = DutchDate::format($date);

                        if ($get('body') === $get('rendered_body')) {
                            $mail = app(MailTemplates::class)->renderCancellation($record, $date);
                            $set('subject', $mail['subject']);
                            $set('body', $mail['body']);
                            $set('rendered_body', $mail['body']);
                        } elseif (filled($old = $get('mail_date'))) {
                            $set('subject', str_replace($old, $new, (string) $get('subject')));
                            $set('body', str_replace($old, $new, (string) $get('body')));
                        }

                        $set('mail_date', $new);
                    }),
                TextInput::make('reason')
                    ->label('Reden (optioneel, alleen intern)')
                    ->maxLength(255),
                Toggle::make('notify')
                    ->label('Lid informeren per e-mail')
                    ->helperText(fn (Member $record): string => filled($record->email)
                        ? "Bevestiging van de opzegging naar {$record->email}."
                        : 'Dit lid heeft geen e-mailadres.')
                    ->disabled(fn (Member $record): bool => blank($record->email))
                    ->live(),
                Hidden::make('rendered_body'),
                Hidden::make('mail_date'),
                ...MailFields::make(fn (Get $get): bool => (bool) $get('notify')),
            ])
            ->authorize('cancel')
            ->action(function (Member $record, array $data): void {
                $cancelledAt = DutchDate::parse($data['cancelled_at']) ?? CarbonImmutable::today();

                app(CancelMember::class)->handle($record, $cancelledAt, $data['reason'] ?? null);

                if (($data['notify'] ?? false) && filled($record->email)) {
                    app(QueueMemberMail::class)->handle($record, MailTemplate::CANCELLATION, $data['subject'], $data['body']);
                }

                Notification::make()
                    ->title('Lid opgezegd per '.DutchDate::format($cancelledAt))
                    ->body(($data['notify'] ?? false) ? "Bevestiging wordt gemaild naar {$record->email}." : null)
                    ->success()
                    ->send();
            });
    }
}
