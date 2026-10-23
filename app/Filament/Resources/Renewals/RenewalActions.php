<?php

namespace App\Filament\Resources\Renewals;

use App\Actions\Memberships\ActivateClubPaidMembership;
use App\Actions\Memberships\MoveUpForRenewal;
use App\Actions\Memberships\RenewalOutcome;
use App\Actions\Memberships\RenewMembership;
use App\Filament\Forms\MailFields;
use App\Filament\Resources\Renewals\Pages\ListRenewals;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Services\Fees;
use App\Support\Money;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Bulk actions on the Verlengingen page, one per tab.
 */
class RenewalActions
{
    /**
     * @return array<int, BulkAction>
     */
    public static function all(): array
    {
        return [self::renew(), self::moveBack(), self::moveUp(), self::remind(), self::activateByClub()];
    }

    public static function moveUp(): BulkAction
    {
        return BulkAction::make('moveUp')
            ->label('Naar Te verlengen')
            ->icon(Heroicon::OutlinedArrowUturnUp)
            ->color('primary')
            ->visible(fn (ListRenewals $livewire): bool => $livewire->activeTab === 'not_yet_due')
            ->authorize('renew', Member::class)
            ->requiresConfirmation()
            ->modalHeading('Naar Te verlengen zetten')
            ->modalDescription('De geselecteerde leden komen bij "Te verlengen" te staan, ook al loopt hun lidmaatschap nog niet binnenkort af. Er wordt nog niets verlengd of gemaild.')
            ->modalSubmitActionLabel('Verplaatsen')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, ListRenewals $livewire): void {
                $moved = $records->filter(fn (Member $member) => app(MoveUpForRenewal::class)->handle($member, $livewire->renewalYear()))->count();

                Notification::make()
                    ->title($moved.' '.($moved === 1 ? 'lid' : 'leden').' naar Te verlengen gezet')
                    ->success()
                    ->send();
            });
    }

    public static function moveBack(): BulkAction
    {
        return BulkAction::make('moveBack')
            ->label('Terug naar Nog niet aan de beurt')
            ->icon(Heroicon::OutlinedArrowUturnDown)
            ->color('gray')
            ->visible(fn (ListRenewals $livewire): bool => $livewire->activeTab === 'due')
            ->authorize('renew', Member::class)
            ->requiresConfirmation()
            ->modalHeading('Terug naar Nog niet aan de beurt')
            ->modalDescription('Alleen leden die met de hand naar "Te verlengen" zijn gezet, gaan terug. Leden van wie het lidmaatschap binnenkort afloopt, blijven staan.')
            ->modalSubmitActionLabel('Terugzetten')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, ListRenewals $livewire): void {
                $moved = $records->filter(fn (Member $member) => app(MoveUpForRenewal::class)->undo($member, $livewire->renewalYear()))->count();
                $skipped = $records->count() - $moved;

                Notification::make()
                    ->title($moved.' '.($moved === 1 ? 'lid' : 'leden').' teruggezet')
                    ->body($skipped ? "{$skipped} overgeslagen (lidmaatschap loopt binnenkort af)." : null)
                    ->success()
                    ->send();
            });
    }

    public static function renew(): BulkAction
    {
        return BulkAction::make('renew')
            ->label(fn (ListRenewals $livewire): string => 'Verlengen voor '.$livewire->renewalYear())
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->visible(fn (ListRenewals $livewire): bool => $livewire->activeTab === 'due')
            ->authorize('renew', Member::class)
            ->modalHeading(fn (ListRenewals $livewire): string => 'Lidmaatschap verlengen voor '.$livewire->renewalYear())
            ->modalDescription(fn (Collection $records): string => self::renewSummary($records))
            ->modalSubmitActionLabel('Verlengen')
            ->modalWidth(Width::ThreeExtraLarge)
            ->fillForm(fn (): array => ['send_mail' => true, ...self::savedText()])
            ->schema([
                Toggle::make('send_mail')
                    ->label('Verlengingsmail met betaallink (iDEAL / Bancontact) versturen')
                    ->helperText('Alleen aan leden die zelf betalen en een e-mailadres hebben.')
                    ->live(),
                ...MailFields::make(fn (Get $get): bool => (bool) $get('send_mail'), MailTemplate::RENEWAL),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, ListRenewals $livewire): void {
                $renew = app(RenewMembership::class);

                try {
                    $outcomes = $records->map(fn (Member $member) => $renew->handle(
                        $member,
                        $livewire->renewalYear(),
                        (bool) $data['send_mail'],
                        $data['subject'] ?? null,
                        $data['body'] ?? null,
                    ));
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title('Niet verlengd')->body($e->getMessage())->danger()->send();

                    return;
                }

                $count = fn (RenewalOutcome $outcome): int => $outcomes->filter(fn (RenewalOutcome $o) => $o === $outcome)->count();
                $renewed = $records->count() - $count(RenewalOutcome::Skipped);

                Notification::make()
                    ->title($renewed.' '.($renewed === 1 ? 'lid' : 'leden').' verlengd')
                    ->body(collect([
                        $count(RenewalOutcome::Mailed) ? $count(RenewalOutcome::Mailed).' krijgen een e-mail met betaallink.' : null,
                        $count(RenewalOutcome::NotMailed) ? $count(RenewalOutcome::NotMailed).' zonder e-mail (betaling staat klaar onder Betalingen).' : null,
                        $count(RenewalOutcome::ViaClub) ? $count(RenewalOutcome::ViaClub).' wachten op betaling door hun vereniging.' : null,
                        $count(RenewalOutcome::Skipped) ? $count(RenewalOutcome::Skipped).' overgeslagen (al verlengd of opgezegd).' : null,
                    ])->filter()->implode(' ') ?: null)
                    ->success()
                    ->send();
            });
    }

    public static function remind(): BulkAction
    {
        return BulkAction::make('remind')
            ->label('Betaallink opnieuw mailen')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (ListRenewals $livewire): bool => $livewire->activeTab === 'awaiting_payment')
            ->authorize('renew', Member::class)
            ->modalHeading('Betaallink opnieuw mailen')
            ->modalDescription('De geselecteerde leden krijgen de verlengingsmail met betaallink (iDEAL / Bancontact) nog een keer. Leden zonder e-mailadres worden overgeslagen.')
            ->modalWidth(Width::ThreeExtraLarge)
            ->fillForm(fn (): array => self::savedText())
            ->schema(MailFields::make(placeholdersFor: MailTemplate::RENEWAL))
            ->modalSubmitActionLabel('Mailen')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, ListRenewals $livewire): void {
                $renew = app(RenewMembership::class);
                $sent = 0;

                foreach ($records as $member) {
                    $payment = RenewMembership::openPaymentFor($member, $livewire->renewalYear());

                    if ($payment && filled($member->email)) {
                        $renew->mail($payment, $data['subject'], $data['body']);
                        $sent++;
                    }
                }

                $skipped = $records->count() - $sent;

                Notification::make()
                    ->title($sent.' '.($sent === 1 ? 'e-mail' : 'e-mails').' verstuurd')
                    ->body($skipped ? "{$skipped} overgeslagen (geen e-mailadres of geen openstaande betaling)." : null)
                    ->success()
                    ->send();
            });
    }

    public static function activateByClub(): BulkAction
    {
        return BulkAction::make('activateByClub')
            ->label('Activeren (betaald door vereniging)')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (ListRenewals $livewire): bool => $livewire->activeTab === 'awaiting_club')
            ->authorize('renew', Member::class)
            ->modalHeading('Betaald door de vereniging')
            ->modalDescription(fn (Collection $records, ListRenewals $livewire): string => 'Lidmaatschap '.$livewire->renewalYear().' activeren voor '
                .$records->countBy(fn (Member $member) => $member->club?->name ?? 'geen vereniging')
                    ->map(fn (int $count, string $club) => "{$count} van {$club}")
                    ->implode(', ')
                .'. Gebruik dit zodra de vereniging de contributie heeft betaald.')
            ->modalSubmitActionLabel('Activeren')
            ->schema([
                Toggle::make('send_card')->label('Lidmaatschapskaarten direct mailen')->default(true),
            ])
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records, array $data, ListRenewals $livewire): void {
                $activate = app(ActivateClubPaidMembership::class);
                $activated = 0;
                $withoutCard = 0;

                foreach ($records as $member) {
                    if ($membership = ActivateClubPaidMembership::pendingFor($member, $livewire->renewalYear())) {
                        $withoutCard += $activate->handle($membership, (bool) $data['send_card']) ? 1 : 0;
                        $activated++;
                    }
                }

                Notification::make()
                    ->title($activated.' '.($activated === 1 ? 'lidmaatschap' : 'lidmaatschappen').' geactiveerd')
                    ->body($data['send_card'] && $withoutCard ? "{$withoutCard} zonder kaart per e-mail (bijv. geen e-mailadres)." : null)
                    ->success()
                    ->send();
            });
    }

    /**
     * The saved "Verlenging" text, with its {placeholders}, to edit for one send.
     *
     * @return array{subject: string, body: string}
     */
    private static function savedText(): array
    {
        $template = MailTemplate::forKey(MailTemplate::RENEWAL);

        return ['subject' => $template->subject, 'body' => $template->body];
    }

    /**
     * "120 leden: 95 krijgen een e-mail met betaallink (iDEAL / Bancontact) (€ 90,00), 20 betalen via hun vereniging (geen betaalverzoek), 5 zonder e-mailadres."
     */
    private static function renewSummary(Collection $records): string
    {
        $records->loadMissing('club');
        $viaClub = $records->filter(fn (Member $member) => $member->paysCollectively());
        $self = $records->diff($viaClub);
        $noEmail = $self->filter(fn (Member $member) => blank($member->email))->count();
        $withEmail = $self->count() - $noEmail;
        $fee = Money::format(app(Fees::class)->annualFeeCents());

        return collect([
            $records->count().' '.($records->count() === 1 ? 'lid' : 'leden').' geselecteerd.',
            $withEmail ? "{$withEmail} betalen zelf: een openstaande betaling van {$fee} en een e-mail met betaallink (iDEAL / Bancontact)." : null,
            $noEmail ? "{$noEmail} betalen zelf maar hebben geen e-mailadres: de betaling van {$fee} wordt wel klaargezet." : null,
            $viaClub->count() ? $viaClub->count().' betalen via hun vereniging ('.$viaClub->countBy(fn (Member $member) => $member->club->name)->map(fn ($n, $club) => "{$club}: {$n}")->implode(', ').'): geen betaalverzoek, wacht op betaling door de vereniging.' : null,
        ])->filter()->implode(' ');
    }
}
