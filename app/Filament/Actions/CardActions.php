<?php

namespace App\Filament\Actions;

use App\Actions\Cards\QueueMembershipCard;
use App\Filament\Forms\MailFields;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Models\Membership;
use App\Services\MailTemplates;
use App\Services\MembershipCard;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Kaart (telefoon)", "Kaart (print)" and "Kaart versturen", usable on a member (current membership) or a membership.
 */
class CardActions
{
    /**
     * @return array<int, Action>
     */
    public static function all(): array
    {
        return [self::download(MembershipCard::PHONE), self::download(MembershipCard::PRINT), self::send()];
    }

    public static function download(string $variant): Action
    {
        return Action::make($variant === MembershipCard::PHONE ? 'cardPhone' : 'cardPrint')
            ->label($variant === MembershipCard::PHONE ? 'Kaart (telefoon)' : 'Kaart (print)')
            ->icon($variant === MembershipCard::PHONE ? Heroicon::OutlinedDevicePhoneMobile : Heroicon::OutlinedPrinter)
            ->color('gray')
            ->visible(fn (Model $record): bool => self::membershipFor($record) !== null)
            ->authorize(fn (Model $record): bool => (bool) auth()->user()?->can('downloadCard', self::membershipFor($record)))
            ->action(function (Model $record) use ($variant): ?StreamedResponse {
                $membership = self::membershipFor($record);
                $card = app(MembershipCard::class);

                try {
                    $pdf = $card->pdf($membership, $variant);
                } catch (RuntimeException $e) {
                    Notification::make()->title('Kaart kan niet worden gemaakt')->body($e->getMessage())->danger()->send();

                    return null;
                }

                return response()->streamDownload(fn () => print ($pdf), $card->filename($membership, $variant), [
                    'Content-Type' => 'application/pdf',
                ]);
            });
    }

    public static function send(): Action
    {
        return Action::make('sendCard')
            ->label('Kaart versturen')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('primary')
            ->modalHeading('Lidmaatschapskaart versturen')
            ->modalDescription(function (Model $record): string {
                $membership = self::membershipFor($record);
                $member = $membership->member;
                $text = "Aan {$member->full_name} <{$member->email}>, met de kaart {$membership->cardYear()} (telefoon- en printversie) als bijlage.";

                return $membership->card_sent_at
                    ? $text.' Let op: deze kaart is al verstuurd op '.$membership->card_sent_at->format('d-m-Y H:i').'.'
                    : $text;
            })
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Versturen')
            ->fillForm(fn (Model $record): array => app(MailTemplates::class)
                ->renderForMembership(MailTemplate::MEMBERSHIP_CARD, self::membershipFor($record)))
            ->schema(MailFields::make())
            ->visible(fn (Model $record): bool => self::membershipFor($record) !== null)
            ->authorize(fn (Model $record): bool => (bool) auth()->user()?->can('sendCard', self::membershipFor($record)))
            ->disabled(fn (Model $record): bool => app(QueueMembershipCard::class)->cannotSendReason(self::membershipFor($record)) !== null)
            ->tooltip(fn (Model $record): ?string => app(QueueMembershipCard::class)->cannotSendReason(self::membershipFor($record)))
            ->action(function (Model $record, array $data): void {
                $membership = self::membershipFor($record);

                if (blank(config('membership_card.qr_secret'))) {
                    Notification::make()->title('QR_SECRET is niet ingesteld')->body('Zonder geheim kunnen geen kaarten worden gemaakt.')->danger()->send();

                    return;
                }

                app(QueueMembershipCard::class)->handle($membership, $data['subject'], $data['body']);

                Notification::make()
                    ->title('Kaart wordt verstuurd')
                    ->body("Naar {$membership->member->email}. Dit gebeurt binnen een paar minuten.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Member → the membership its card is for (latest active); membership → itself.
     */
    private static function membershipFor(Model $record): ?Membership
    {
        return match (true) {
            $record instanceof Membership => $record->isActive() ? $record : null,
            $record instanceof Member => $record->currentMembership,
            default => null,
        };
    }
}
