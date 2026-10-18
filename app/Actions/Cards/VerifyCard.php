<?php

namespace App\Actions\Cards;

use App\Models\Member;
use App\Models\Membership;
use App\Services\MembershipCard;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Checks a scanned card: is the QR signature genuine, and is the member's membership valid today?
 */
class VerifyCard
{
    /**
     * @param  array{l?: mixed, n?: mixed, j?: mixed, c?: mixed}  $input
     */
    public function handle(array $input, ?Carbon $today = null): CardVerification
    {
        $today ??= today();
        [$number, $name, $year, $signature] = array_map(
            fn ($value) => is_string($value) ? trim($value) : '',
            [$input['l'] ?? null, $input['n'] ?? null, $input['j'] ?? null, $input['c'] ?? null],
        );

        if ($number === '' || $name === '' || $year === '' || $signature === '') {
            return $this->invalid('Ongeldige kaart', 'De QR-code is onvolledig.');
        }

        try {
            $genuine = MembershipCard::hasValidSignature($number, $name, $year, $signature);
        } catch (RuntimeException) {
            return $this->invalid('Controle niet mogelijk', 'De kaartcontrole is nog niet ingesteld. Neem contact op met de ledenadministratie.');
        }

        if (! $genuine) {
            return $this->invalid('Ongeldige kaart', 'Deze kaart is niet door '.config('membership_card.association_name').' uitgegeven of is aangepast.');
        }

        $member = Member::with('currentMembership')->firstWhere('member_number', (int) $number);

        if (! $member) {
            return $this->invalid('Onbekend lid', 'Dit lidnummer komt niet voor in de ledenadministratie.');
        }

        if ($member->hasLeft($today)) {
            return $this->invalid('Lidmaatschap opgezegd', 'Opgezegd per '.$member->cancelled_at->format('d-m-Y').'.', $member);
        }

        $valid = $member->memberships()->validOn($today)->orderByDesc('ends_at')->first();

        if (! $valid) {
            $last = $member->currentMembership;

            return $this->invalid(
                'Lidmaatschap verlopen',
                $last && $last->ends_at->lt($today) ? 'Verlopen op '.$last->ends_at->format('d-m-Y').'.' : 'Er is geen geldig lidmaatschap gevonden.',
                $member,
            );
        }

        $notice = (int) $year < $valid->cardYear()
            ? "Let op: dit is een oude kaart ({$year}). Het lidmaatschap is wel geldig."
            : null;

        return new CardVerification(
            valid: true,
            title: 'Geldig lid',
            message: 'Geldig t/m '.$this->validUntil($member, $valid)->format('d-m-Y'),
            member: $member,
            membership: $valid,
            notice: $notice,
        );
    }

    /**
     * The end of the unbroken run of active periods that includes today (e.g. a December member: 31-12 next year).
     */
    private function validUntil(Member $member, Membership $valid): Carbon
    {
        $until = $valid->ends_at;

        foreach ($member->memberships()->active()->whereDate('starts_at', '>', $valid->starts_at)->orderBy('starts_at')->get() as $next) {
            if ($next->starts_at->gt($until->copy()->addDay())) {
                break;
            }

            $until = $until->max($next->ends_at);
        }

        return $until;
    }

    private function invalid(string $title, string $message, ?Member $member = null): CardVerification
    {
        return new CardVerification(valid: false, title: $title, message: $message, member: $member);
    }
}
