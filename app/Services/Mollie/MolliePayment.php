<?php

namespace App\Services\Mollie;

use Carbon\CarbonImmutable;

/**
 * A payment as Mollie reports it (only the fields we use).
 */
final readonly class MolliePayment
{
    public function __construct(
        public string $id,
        public string $status,
        public int $amountCents,
        public ?int $paymentId,
        public ?CarbonImmutable $paidAt = null,
        public ?string $method = null,
        public ?string $checkoutUrl = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  A payment object from the Mollie API.
     */
    public static function fromApi(array $data): self
    {
        $paymentId = $data['metadata']['payment_id'] ?? null;

        return new self(
            id: (string) $data['id'],
            status: (string) $data['status'],
            amountCents: (int) round(((float) ($data['amount']['value'] ?? 0)) * 100),
            paymentId: is_numeric($paymentId) ? (int) $paymentId : null,
            paidAt: isset($data['paidAt']) ? CarbonImmutable::parse($data['paidAt'])->setTimezone(config('app.timezone')) : null,
            method: $data['method'] ?? null,
            checkoutUrl: $data['_links']['checkout']['href'] ?? null,
        );
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Started but not finished: the member can still complete it at the checkout URL.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'pending'], true);
    }

    /**
     * Canceled, expired or failed: the member has to start again.
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, ['canceled', 'expired', 'failed'], true);
    }
}
