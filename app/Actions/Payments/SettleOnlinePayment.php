<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Mollie\Mollie;
use App\Services\Mollie\MolliePayment;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Processes the status of a Mollie payment: once paid, the payment is marked paid as usual (memberships active,
 * confirmation and card e-mailed). Called by the webhook and by the page members return to; safe to call twice.
 */
class SettleOnlinePayment
{
    public function __construct(private Mollie $mollie, private MarkPaymentPaid $markPaid) {}

    /**
     * Fetches the payment from Mollie: the webhook only tells us an id, never the status.
     *
     * @throws RequestException
     */
    public function handle(string $mollieId): ?MolliePayment
    {
        $molliePayment = $this->mollie->getPayment($mollieId);
        $this->settle($molliePayment);

        return $molliePayment;
    }

    public function settle(MolliePayment $molliePayment): ?Payment
    {
        $payment = $molliePayment->paymentId ? Payment::find($molliePayment->paymentId) : null;

        if (! $payment || ! $molliePayment->isPaid()) {
            return $payment;
        }

        // The webhook and the return page can arrive at the same moment.
        return Cache::lock("payment-settle:{$payment->id}", 60)->block(15, function () use ($payment, $molliePayment) {
            $payment->refresh();

            if ($payment->status === PaymentStatus::Paid) {
                return $payment;
            }

            if (! $payment->isOpen() || $molliePayment->amountCents !== $payment->amount_cents) {
                $this->logUnmatched($payment, $molliePayment);

                return $payment;
            }

            $this->markPaid->handle($payment, PaymentMethod::fromMollie($molliePayment->method), $molliePayment->paidAt, $molliePayment->id);

            return $payment->refresh();
        });
    }

    /**
     * Money came in for a payment that was cancelled in the meantime, or for a different amount: the treasurer sorts it out.
     */
    private function logUnmatched(Payment $payment, MolliePayment $molliePayment): void
    {
        activity('payments')
            ->performedOn($payment)
            ->event('online_payment_unmatched')
            ->withProperties(['mollie_id' => $molliePayment->id, 'amount_cents' => $molliePayment->amountCents, 'status' => $payment->status->value])
            ->log('Online betaling ontvangen die niet verwerkt kon worden (betaling niet meer openstaand of ander bedrag); controleer in Mollie');

        Log::warning('Mollie payment could not be matched', ['payment_id' => $payment->id, 'mollie_id' => $molliePayment->id]);
    }
}
