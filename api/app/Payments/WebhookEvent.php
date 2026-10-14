<?php

namespace App\Payments;

/**
 * A normalised, signature-verified webhook from a payment gateway. `reference`
 * carries our own invoice payment token so the receiver can match the event to
 * a document without trusting provider-specific identifiers.
 */
final readonly class WebhookEvent
{
    public function __construct(
        public string $provider,
        public string $id,
        public string $type,
        public bool $paid,
        public float $amount,
        public string $currency,
        public ?string $reference,
        public ?string $transactionId = null,
    ) {}
}
