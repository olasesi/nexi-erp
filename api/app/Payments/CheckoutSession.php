<?php

namespace App\Payments;

/**
 * Result of starting a checkout with a gateway. When `completed` is true the
 * gateway settled the payment inline (the offline stub does this) and no
 * redirect is needed; otherwise the customer is sent to `url`.
 */
final readonly class CheckoutSession
{
    public function __construct(
        public string $provider,
        public string $id,
        public string $url,
        public float $amount,
        public string $currency,
        public string $reference,
        public bool $completed = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'session_id' => $this->id,
            'checkout_url' => $this->url,
            'amount' => round($this->amount, 2),
            'currency' => $this->currency,
            'reference' => $this->reference,
            'completed' => $this->completed,
        ];
    }
}
