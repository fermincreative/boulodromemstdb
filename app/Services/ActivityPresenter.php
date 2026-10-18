<?php

namespace App\Services;

use App\Filament\Resources\Members\MemberResource;
use App\Models\CardYearStyle;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Models\Membership;
use App\Models\Payment;
use App\Support\Money;
use Spatie\Activitylog\Models\Activity;

/**
 * Turns activity log entries into short Dutch sentences for the dashboard and the log page.
 * Changed fields are named, never their values, so no personal data ends up on the dashboard.
 */
class ActivityPresenter
{
    /** @var array<string, string> */
    private const FIELD_LABELS = [
        'member_number' => 'lidnummer',
        'first_name' => 'voornaam',
        'middle_name' => 'tussenvoegsel',
        'last_name' => 'achternaam',
        'email' => 'e-mailadres',
        'phone_mobile' => 'mobiel',
        'phone_home' => 'telefoon thuis',
        'street' => 'adres',
        'postal_code' => 'postcode',
        'city' => 'woonplaats',
        'country' => 'land',
        'birthday' => 'verjaardag',
        'club_id' => 'vereniging',
        'contact_for' => 'contactpersoon van',
        'joined_at' => 'lid sinds',
        'cancelled_at' => 'opgezegd op',
        'notes' => 'notities',
        'starts_at' => 'ingangsdatum',
        'ends_at' => 'einddatum',
        'status' => 'status',
        'card_sent_at' => 'kaart verstuurd',
        'subject' => 'onderwerp',
        'body' => 'bericht',
        'badge_color' => 'achtergrond jaarvak',
        'text_color' => 'kleur jaartal',
        'year' => 'jaar',
    ];

    /** Log names shown as filter options on the log page. */
    public const LOG_NAMES = [
        'members' => 'Leden',
        'memberships' => 'Lidmaatschappen',
        'payments' => 'Betalingen',
        'settings' => 'Instellingen',
    ];

    public function description(Activity $activity): string
    {
        $subject = $activity->subject;

        $text = match ([$activity->log_name, $activity->event]) {
            ['members', 'created'] => 'Lid toegevoegd',
            ['members', 'updated'] => 'Lidgegevens gewijzigd',
            ['members', 'deleted'] => 'Lid verwijderd',
            ['members', 'exported'] => $activity->description,
            ['memberships', 'created'] => 'Lidmaatschap toegevoegd'.($subject instanceof Membership ? " ({$subject->cardYear()})" : ''),
            ['memberships', 'updated'] => 'Lidmaatschap gewijzigd'.($subject instanceof Membership ? " ({$subject->cardYear()})" : ''),
            ['memberships', 'deleted'] => 'Lidmaatschap verwijderd',
            ['memberships', 'card_sent'] => 'Lidmaatschapskaart '.($activity->properties['year'] ?? '').' verstuurd',
            ['memberships', 'card_failed'] => 'Versturen lidmaatschapskaart mislukt',
            ['settings', 'updated'] => match (true) {
                $subject instanceof MailTemplate => "E-mailtekst \"{$subject->name}\" gewijzigd",
                $subject instanceof CardYearStyle => "Kaartkleuren {$subject->year} gewijzigd",
                default => 'Instelling gewijzigd',
            },
            ['settings', 'rates_updated'] => 'Tarieven gewijzigd',
            ['payments', 'created'] => 'Betaling aangemaakt: '.($subject instanceof Payment ? Money::format($subject->amount_cents) : ''),
            ['payments', 'paid'] => 'Betaling ontvangen: '.Money::format((int) ($activity->properties['amount_cents'] ?? 0)),
            ['payments', 'cancelled'] => 'Betaling geannuleerd',
            ['payments', 'updated'] => 'Betaling gewijzigd',
            ['settings', 'created'] => $subject instanceof CardYearStyle ? "Kaartkleuren {$subject->year} toegevoegd" : 'Instelling toegevoegd',
            ['settings', 'deleted'] => 'Kaartkleuren '.($activity->properties['old']['year'] ?? '').' verwijderd',
            default => $activity->description,
        };

        if ($activity->event === 'updated' && ($fields = $this->changedFields($activity)) !== []) {
            $text .= ': '.implode(', ', $fields);
        }

        if (in_array($activity->event, ['card_sent', 'mail_sent'], true) && filled($activity->properties['email'] ?? null)) {
            $text .= ' naar '.$activity->properties['email'];
        }

        return trim($text);
    }

    /**
     * "Florian Fermin (1)" for anything that belongs to a member; null when there is no member (e.g. an export).
     */
    public function memberLabel(Activity $activity): ?string
    {
        $member = $this->member($activity);

        return $member ? "{$member->full_name} ({$member->display_number})" : null;
    }

    public function memberUrl(Activity $activity): ?string
    {
        $member = $this->member($activity);

        return $member ? MemberResource::getUrl('view', ['record' => $member]) : null;
    }

    public function causerName(Activity $activity): string
    {
        return $activity->causer?->name ?? 'Systeem';
    }

    /**
     * Colour of the event badge.
     */
    public function color(Activity $activity): string
    {
        return match ($activity->event) {
            'created', 'reinstated', 'paid', 'signed_up' => 'success',
            'card_sent', 'mail_sent' => 'info',
            'cancelled', 'deleted', 'card_failed', 'mail_failed' => 'danger',
            'exported' => 'warning',
            default => 'gray',
        };
    }

    /**
     * @return array<int, string>
     */
    private function changedFields(Activity $activity): array
    {
        $changed = array_keys($activity->properties['attributes'] ?? []);

        return array_values(array_map(fn (string $field) => self::FIELD_LABELS[$field] ?? $field, $changed));
    }

    private function member(Activity $activity): ?Member
    {
        $subject = $activity->subject;

        return match (true) {
            $subject instanceof Member => $subject,
            $subject instanceof Membership => $subject->member,
            $subject instanceof Payment => $subject->member,
            default => null,
        };
    }
}
