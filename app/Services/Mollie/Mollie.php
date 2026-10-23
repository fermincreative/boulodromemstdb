<?php

namespace App\Services\Mollie;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Minimal client for the Mollie payments API (https://docs.mollie.com/reference/payments-api).
 */
class Mollie
{
    private const API_URL = 'https://api.mollie.com/v2/';

    public function isConfigured(): bool
    {
        return filled(config('services.mollie.key'));
    }

    /**
     * The Mollie method ids offered at checkout (MOLLIE_METHODS), e.g. ["ideal", "bancontact"].
     *
     * @return array<int, string>
     */
    public function methods(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) config('services.mollie.methods')))));
    }

    /**
     * "iDEAL of Bancontact": how the online payment options are named on the pages members see.
     */
    public function methodsLabel(): string
    {
        $labels = array_map(fn (string $method) => PaymentMethod::tryFrom($method)?->getLabel() ?? ucfirst($method), $this->methods() ?: ['ideal']);

        return count($labels) > 1 ? implode(', ', array_slice($labels, 0, -1)).' of '.end($labels) : $labels[0];
    }

    /**
     * Starts a Mollie payment for an open payment. The member pays at the returned checkout URL.
     *
     * @throws RequestException
     */
    public function createPayment(Payment $payment, string $redirectUrl): MolliePayment
    {
        $data = $this->client()->post('payments', array_filter([
            'amount' => ['currency' => 'EUR', 'value' => number_format($payment->amount_cents / 100, 2, '.', '')],
            'description' => Str::limit("{$payment->description} {$payment->member->display_number}", 255, ''),
            'redirectUrl' => $redirectUrl,
            'webhookUrl' => $this->webhookUrl(),
            'method' => $this->methods() ?: null,
            'locale' => 'nl_NL',
            'metadata' => ['payment_id' => $payment->id],
        ]))->throw()->json();

        return MolliePayment::fromApi($data);
    }

    /**
     * @throws RequestException
     */
    public function getPayment(string $id): MolliePayment
    {
        return MolliePayment::fromApi($this->client()->get('payments/'.rawurlencode($id))->throw()->json());
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(self::API_URL)
            ->withToken((string) config('services.mollie.key'))
            ->acceptJson()
            ->timeout(15);
    }

    /**
     * Mollie refuses webhook URLs it cannot reach, so none is sent from a local machine (the return page checks the status too).
     */
    private function webhookUrl(): ?string
    {
        $url = config('services.mollie.webhook_url') ?: route('mollie.webhook');
        $host = (string) parse_url($url, PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.test') ? null : $url;
    }
}
