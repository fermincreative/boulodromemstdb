<?php

namespace App\Actions\Memberships;

use App\Actions\Payments\CancelPayment;
use App\Enums\MembershipStatus;
use App\Models\Membership;
use InvalidArgumentException;

/**
 * Withdraws a membership that is still awaiting payment, together with its open payment.
 */
class CancelPendingMembership
{
    public function __construct(private CancelPayment $cancelPayment) {}

    public function handle(Membership $membership, ?string $reason = null): void
    {
        if ($membership->status !== MembershipStatus::Pending) {
            throw new InvalidArgumentException('Alleen een lidmaatschap dat wacht op betaling kan worden geannuleerd.');
        }

        if ($membership->payment?->isOpen()) {
            $this->cancelPayment->handle($membership->payment, $reason);

            return;
        }

        $membership->update(['status' => MembershipStatus::Cancelled]);
    }
}
