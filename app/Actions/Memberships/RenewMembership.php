<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Jobs\SendMemberMail;
use App\Models\MailTemplate;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Fees;
use App\Services\MailTemplates;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Renews a member for a calendar year: a new period (1-1 to 31-12) awaiting payment.
 *
 * - Paying themselves: an open payment for the annual fee, and the "Verlenging" e-mail with the payment link.
 * - Member of a club that pays collectively: no payment and no e-mail; the period is activated once the club has paid.
 *
 * Running it twice for the same member and year creates nothing extra.
 */
class RenewMembership
{
    public function __construct(private Fees $fees, private MailTemplates $templates) {}

    /**
     * The year renewals are for: next year from October on, otherwise this year (for late renewals).
     */
    public static function defaultYear(): int
    {
        return today()->month >= 10 ? today()->year + 1 : today()->year;
    }

    /**
     * How many days before the end of a membership it shows up under "Te verlengen" (Beheer → Tarieven).
     */
    public static function windowDays(): int
    {
        return (int) Setting::get(Setting::RENEWAL_WINDOW_DAYS, 42);
    }

    /**
     * Memberships ending on or before this date are up for renewal.
     */
    public static function cutoff(): CarbonImmutable
    {
        return CarbonImmutable::today()->addDays(self::windowDays());
    }

    /**
     * @param  string|null  $subject  Subject with {placeholders} for this send only (default: the saved "Verlenging" text).
     * @param  string|null  $body  Message with {placeholders} for this send only.
     */
    public function handle(Member $member, int $year, bool $sendMail = true, ?string $subject = null, ?string $body = null): RenewalOutcome
    {
        $member->loadMissing('club');
        $viaClub = $member->paysCollectively();
        $feeCents = $this->fees->annualFeeCents();

        if (! $viaClub && $feeCents <= 0) {
            throw new InvalidArgumentException('Stel eerst de jaarcontributie in (Beheer → Tarieven).');
        }

        $payment = DB::transaction(function () use ($member, $year, $viaClub, $feeCents): Payment|false|null {
            if (! Member::whereKey($member->getKey())->renewable($year)->exists()) {
                return false;
            }

            $payment = $viaClub ? null : $member->payments()->create([
                'description' => "Contributie {$year}",
                'amount_cents' => $feeCents,
                'status' => PaymentStatus::Open,
            ]);

            // A renewal for this year that was withdrawn earlier is reused: (member_id, starts_at) is unique.
            $membership = $member->memberships()->whereDate('starts_at', "{$year}-01-01")->first()
                ?? $member->memberships()->make(['starts_at' => "{$year}-01-01"]);

            $membership->fill([
                'payment_id' => $payment?->id,
                'ends_at' => "{$year}-12-31",
                'status' => MembershipStatus::Pending,
                'card_sent_at' => null,
            ])->save();

            activity('members')
                ->performedOn($member)
                ->event('renewed')
                ->withProperties(array_filter(['year' => $year, 'paid_by_club' => $viaClub ? $member->club->name : null]))
                ->log($viaClub ? "Verlengd voor {$year} (betaling via {$member->club->name})" : "Verlengd voor {$year}");

            return $payment;
        });

        if ($payment === false) {
            return RenewalOutcome::Skipped;
        }

        if ($payment === null) {
            return RenewalOutcome::ViaClub;
        }

        if (! $sendMail || blank($member->email)) {
            return RenewalOutcome::NotMailed;
        }

        $this->mail($payment, $subject, $body);

        return RenewalOutcome::Mailed;
    }

    /**
     * (Re)sends the "Verlenging" e-mail for an open renewal payment, e.g. as a reminder.
     */
    public function mail(Payment $payment, ?string $subject = null, ?string $body = null): void
    {
        if (! $payment->isOpen()) {
            throw new InvalidArgumentException('Deze betaling staat niet meer open.');
        }

        if (blank($payment->member->email)) {
            throw new InvalidArgumentException('Dit lid heeft geen e-mailadres.');
        }

        $mail = $this->templates->renderRenewal($payment, $subject, $body);
        SendMemberMail::dispatch($payment->member, MailTemplate::RENEWAL, $mail['subject'], $mail['body'], auth()->id());
    }

    /**
     * The open payment of a member's renewal for $year, if there is one.
     */
    public static function openPaymentFor(Member $member, int $year): ?Payment
    {
        return $member->payments()
            ->where('status', PaymentStatus::Open)
            ->whereHas('memberships', fn ($memberships) => $memberships
                ->where('status', MembershipStatus::Pending)
                ->whereDate('starts_at', "{$year}-01-01"))
            ->latest('id')
            ->first();
    }
}
