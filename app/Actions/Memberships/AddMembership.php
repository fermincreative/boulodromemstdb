<?php

namespace App\Actions\Memberships;

use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Models\Membership;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Adds a membership period by hand. It always starts as "awaiting payment" with an open payment:
 * a membership only becomes active through a registered payment.
 */
class AddMembership
{
    public function handle(Member $member, CarbonInterface $startsAt, CarbonInterface $endsAt, int $amountCents): Membership
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('Vul een bedrag in.');
        }

        if ($member->isCancelled()) {
            throw new InvalidArgumentException('Dit lid is opgezegd.');
        }

        return DB::transaction(function () use ($member, $startsAt, $endsAt, $amountCents) {
            $years = array_unique([$startsAt->year, $endsAt->year]);

            $payment = $member->payments()->create([
                'description' => 'Contributie '.implode('–', $years),
                'amount_cents' => $amountCents,
                'status' => PaymentStatus::Open,
            ]);

            return $member->memberships()->create([
                'payment_id' => $payment->id,
                'starts_at' => $startsAt->toDateString(),
                'ends_at' => $endsAt->toDateString(),
                'status' => MembershipStatus::Pending,
            ]);
        });
    }
}
