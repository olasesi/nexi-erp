<?php

namespace App\Contracts;

use App\Models\Invoice;
use App\Payments\CheckoutSession;
use App\Payments\WebhookEvent;

/**
 * A hosted payment gateway. Concrete drivers translate our invoice/payment
 * domain into a provider's checkout API and back again, so controllers never
 * know which provider (if any) is wired in.
 */
interface PaymentGateway
{
    /**
     * Gateway identifier, e.g. "stub" or "stripe".
     */
    public function provider(): string;

    /**
     * Start a checkout for the given invoice and amount, returning where to
     * send the customer (or a completed session for offline gateways).
     */
    public function createCheckout(
        Invoice $invoice,
        float $amount,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession;

    /**
     * Verify and normalise an inbound webhook. Returns null when the payload
     * fails signature verification or cannot be understood.
     *
     * @param  array<string, string>  $headers
     */
    public function parseWebhook(string $payload, array $headers): ?WebhookEvent;
}
