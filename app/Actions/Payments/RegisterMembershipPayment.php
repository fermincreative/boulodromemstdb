<?php

namespace App\Actions\Payments;

use App\Enums\MembershipStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Membership;
use App\Services\Fees;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * "Markeer als betaald" from a membership: pays its open payment, or, for an older membership without one,
 * first records a payment. Either way the normal payment process runs (activation, confirmation, card).
 */
class RegisterMembershipPayment
{
    public function __construct(private MarkPaymentPaid $markPaid, private Fees $fees) {}

    public function handle(
        Membership $membership,
        PaymentMethod $method,
        ?CarbonInterface $paidAt = null,
        ?string $reference = null,
        bool $sendCard = true,
        bool $sendConfirmation = true,
        ?int $amountCents = null,
    ): ?string {
        if ($membership->status !== MembershipStatus::Pending) {
            throw new InvalidArgumentException('Alleen een lidmaatschap dat wacht op betaling kan als betaald worden gemarkeerd.');
        }

        $payment = $membership->payment?->isOpen() ? $membership->payment : null;

        if (! $payment) {
            $payment = DB::transaction(function () use ($membership, $amountCents) {
                $payment = $membership->member->payments()->create([
                    'description' => 'Contributie '.$membership->cardYear(),
                    'amount_cents' => $amountCents ?? $this->fees->annualFeeCents(),
                    'status' => PaymentStatus::Open,
                ]);
                $membership->update(['payment_id' => $payment->id]);

                return $payment;
            });
        }

        return $this->markPaid->handle($payment, $method, $paidAt, $reference, $sendCard, $sendConfirmation);
    }
}
