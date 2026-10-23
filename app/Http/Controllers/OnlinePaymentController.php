<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SettleOnlinePayment;
use App\Actions\Payments\StartOnlinePayment;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\Mollie\Mollie;
use App\Services\Mollie\MolliePayment;
use App\Support\PaymentLink;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Public payment page behind the link in the aanmelding and verlenging e-mails (signed URLs).
 * The page itself creates nothing: link scanners in mail programs open links too. Paying starts with a button.
 */
class OnlinePaymentController extends Controller
{
    public function show(Payment $payment, Mollie $mollie): View
    {
        return $this->page($payment, $mollie);
    }

    public function start(Payment $payment, StartOnlinePayment $start, Mollie $mollie): RedirectResponse|View
    {
        if (! $payment->isOpen() || ! $mollie->isConfigured()) {
            return redirect(PaymentLink::for($payment));
        }

        try {
            return redirect()->away($start->handle($payment), 303);
        } catch (RequestException|InvalidArgumentException $e) {
            Log::error('Could not start Mollie payment', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            return $this->page($payment, $mollie, error: __('payment.unavailable'));
        }
    }

    /**
     * Where Mollie sends the member back. The status is checked here too, so it does not depend on the webhook.
     */
    public function return(Payment $payment, SettleOnlinePayment $settle, Mollie $mollie): View
    {
        $molliePayment = null;

        if ($payment->mollie_id && $mollie->isConfigured()) {
            try {
                $molliePayment = $settle->handle($payment->mollie_id);
                $payment->refresh();
            } catch (RequestException $e) {
                Log::error('Could not check Mollie payment', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            }
        }

        return $this->page($payment, $mollie, returned: $molliePayment ?? true);
    }

    /**
     * @param  MolliePayment|bool  $returned  Back from Mollie (with the status Mollie reported, if known).
     */
    private function page(Payment $payment, Mollie $mollie, MolliePayment|bool $returned = false, ?string $error = null): View
    {
        $payment->loadMissing('member');

        return view('payments.show', [
            'payment' => $payment,
            'member' => $payment->member,
            'state' => match (true) {
                $payment->status === PaymentStatus::Paid => 'paid',
                ! $payment->isOpen() => 'closed',
                $returned instanceof MolliePayment && $returned->isOpen() => 'processing',
                $returned instanceof MolliePayment && $returned->hasFailed() => 'failed',
                default => 'open',
            },
            'canPayOnline' => $mollie->isConfigured(),
            'onlineMethods' => $mollie->methodsLabel(),
            'startUrl' => PaymentLink::start($payment),
            'error' => $error,
            'iban' => Setting::get(Setting::BANK_IBAN),
            'holder' => Setting::get(Setting::BANK_ACCOUNT_HOLDER),
            'reference' => 'Contributie '.(substr((string) $payment->memberships()->min('starts_at'), 0, 4) ?: today()->year).' '.$payment->member->display_number,
        ]);
    }
}
