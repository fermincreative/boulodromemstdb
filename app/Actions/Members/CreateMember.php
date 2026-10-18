<?php

namespace App\Actions\Members;

use App\Actions\Payments\MarkPaymentPaid;
use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Member;
use App\Services\Fees;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Creates a member, optionally with a first membership (today until 31 December) and an open payment
 * at the joining rate. A December sign-up also gets next year's membership, in the same payment.
 */
class CreateMember
{
    public function __construct(private Fees $fees, private MarkPaymentPaid $markPaid) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @param  PaymentMethod|null  $paidWith  Paid on the spot: the payment is marked paid; confirmation and card are e-mailed.
     */
    public function handle(array $attributes, bool $withMembership = false, ?PaymentMethod $paidWith = null, bool $notify = true): Member
    {
        [$member, $payment] = DB::transaction(function () use ($attributes, $withMembership) {
            $member = Member::create($attributes);

            if (! $withMembership) {
                return [$member, null];
            }

            $quote = $this->fees->quoteForNewMember();

            $payment = $member->payments()->create([
                'description' => $quote->description,
                'amount_cents' => $quote->totalCents(),
                'status' => PaymentStatus::Open,
            ]);

            foreach ($quote->periods as $period) {
                $member->memberships()->create([
                    'payment_id' => $payment->id,
                    'starts_at' => $period['starts_at'],
                    'ends_at' => $period['ends_at'],
                    'status' => MembershipStatus::Pending,
                ]);
            }

            return [$member, $payment];
        });

        if ($payment && $paidWith) {
            $this->markPaid->handle($payment, $paidWith, sendCard: $notify, sendConfirmation: $notify);
        }

        return $member;
    }

    /**
     * A new member's first period: today up to and including 31 December of the current year.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function firstPeriod(): array
    {
        $today = CarbonImmutable::today();

        return [$today, $today->endOfYear()->startOfDay()];
    }
}
