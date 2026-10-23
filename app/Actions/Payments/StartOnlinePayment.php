<?php

namespace App\Actions\Payments;

use App\Models\Payment;
use App\Services\Mollie\Mollie;
use App\Support\PaymentLink;
use Illuminate\Http\Client\RequestException;
use InvalidArgumentException;

/**
 * Sends a member to Mollie to pay an open payment online (iDEAL, Bancontact). Returns the URL to send them to.
 */
class StartOnlinePayment
{
    public function __construct(private Mollie $mollie, private SettleOnlinePayment $settle) {}

    /**
     * @throws RequestException
     */
    public function handle(Payment $payment): string
    {
        if (! $payment->isOpen()) {
            throw new InvalidArgumentException('Deze betaling staat niet meer open.');
        }

        // Clicked the link again: continue the checkout started before, or show it was paid after all.
        if ($payment->mollie_id) {
            $previous = $this->mollie->getPayment($payment->mollie_id);

            if ($previous->isPaid()) {
                $this->settle->settle($previous);

                return PaymentLink::return($payment);
            }

            if ($previous->status === 'open' && $previous->checkoutUrl && $previous->amountCents === $payment->amount_cents) {
                return $previous->checkoutUrl;
            }
        }

        $molliePayment = $this->mollie->createPayment($payment, PaymentLink::return($payment));
        $payment->update(['mollie_id' => $molliePayment->id]);

        return $molliePayment->checkoutUrl ?? PaymentLink::return($payment);
    }
}
