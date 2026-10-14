<?php

namespace App\Payments\Drivers;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use App\Payments\CheckoutSession;
use App\Payments\WebhookEvent;

/**
 * Offline, deterministic gateway used in tests and before a real provider is
 * configured. It settles any checkout immediately, so the caller can record the
 * payment without a redirect or callback.
 */
class StubPaymentGateway implements PaymentGateway
{
    public function provider(): string
    {
        return 'stub';
    }

    public function createCheckout(
        Invoice $invoice,
        float $amount,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $reference = (string) ($invoice->payment_token ?? '');

        return new CheckoutSession(
            provider: $this->provider(),
            id: 'stub_'.$invoice->id,
            url: $successUrl,
            amount: $amount,
            currency: strtoupper((string) ($invoice->currency ?? 'USD')),
            reference: $reference,
            completed: true,
        );
    }

    public function parseWebhook(string $payload, array $headers): ?WebhookEvent
    {
        return null;
    }
}
