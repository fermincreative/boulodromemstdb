<?php

namespace App\Actions\Payments;

use App\Enums\MembershipStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Withdraws an open payment request; the periods it was for, still awaiting payment, are cancelled too.
 */
class CancelPayment
{
    public function handle(Payment $payment, ?string $reason = null): void
    {
        if (! $payment->isOpen()) {
            throw new InvalidArgumentException('Alleen een openstaande betaling kan worden geannuleerd.');
        }

        DB::transaction(function () use ($payment, $reason) {
            $payment->update(['status' => PaymentStatus::Cancelled]);

            $payment->memberships()
                ->where('status', MembershipStatus::Pending)
                ->get()
                ->each->update(['status' => MembershipStatus::Cancelled]);

            activity('payments')
                ->performedOn($payment)
                ->event('cancelled')
                ->withProperties(array_filter(['reason' => $reason]))
                ->log('Betaling geannuleerd');
        });
    }
}
