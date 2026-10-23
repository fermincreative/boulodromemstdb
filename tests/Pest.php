<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * A payment object as the Mollie API returns it.
 *
 * @return array<string, mixed>
 */
function molliePayment(string $id, string $status, string $amount, ?int $paymentId, array $overrides = []): array
{
    return array_replace_recursive([
        'resource' => 'payment',
        'id' => $id,
        'status' => $status,
        'amount' => ['currency' => 'EUR', 'value' => $amount],
        'method' => 'ideal',
        'metadata' => ['payment_id' => $paymentId],
        'paidAt' => $status === 'paid' ? '2026-10-20T10:15:00+00:00' : null,
        '_links' => $status === 'open' ? ['checkout' => ['href' => "https://www.mollie.com/checkout/{$id}"]] : [],
    ], $overrides);
}
