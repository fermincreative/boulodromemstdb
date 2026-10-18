<?php

namespace App\Actions\Members;

use App\Actions\Payments\CancelPayment;
use App\Enums\MembershipStatus;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cancels a membership ("opzeggen"). By default it ends when the paid period ends (normally 31 December).
 * Cancelled members get no renewals and no cards; periods after the end date that were not paid yet are withdrawn.
 */
class CancelMember
{
    public function __construct(private CancelPayment $cancelPayment) {}

    public function handle(Member $member, ?CarbonInterface $on = null, ?string $reason = null): Member
    {
        if ($member->isCancelled()) {
            throw new InvalidArgumentException("Lid {$member->display_number} is al opgezegd.");
        }

        $on = CarbonImmutable::instance($on ?? self::defaultDate($member))->startOfDay();

        DB::transaction(function () use ($member, $on, $reason) {
            $member->forceFill(['cancelled_at' => $on])->saveQuietly();

            // A renewal awaiting payment for after the end date is no longer needed.
            $member->memberships()
                ->where('status', MembershipStatus::Pending)
                ->whereDate('starts_at', '>', $on)
                ->with('payment')
                ->get()
                ->each(function ($membership) {
                    if ($membership->payment?->isOpen()) {
                        $this->cancelPayment->handle($membership->payment, 'Lid opgezegd');
                    } else {
                        $membership->update(['status' => MembershipStatus::Cancelled]);
                    }
                });

            activity('members')
                ->performedOn($member)
                ->event('cancelled')
                ->withProperties(array_filter(['cancelled_at' => $on->toDateString(), 'reason' => $reason]))
                ->log('Lidmaatschap opgezegd');
        });

        return $member;
    }

    /**
     * The end of the period the member has paid for; 31 December of this year when nothing runs past today.
     */
    public static function defaultDate(Member $member): CarbonImmutable
    {
        $paidUntil = $member->memberships()->active()->max('ends_at');
        $endOfYear = CarbonImmutable::today()->endOfYear()->startOfDay();

        if (! $paidUntil) {
            return $endOfYear;
        }

        return CarbonImmutable::parse($paidUntil)->startOfDay()->max($endOfYear);
    }
}
