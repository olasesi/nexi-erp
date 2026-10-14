<?php

namespace App\Payments\Drivers;

use App\Contracts\PaymentGateway;
use App\Models\Invoice;
use App\Payments\CheckoutSession;
use App\Payments\WebhookEvent;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Stripe Checkout driver. Creates a hosted Checkout Session over the Stripe
 * REST API and verifies inbound webhooks with the endpoint signing secret, so
 * no SDK is required. Credentials come from the company's `stripe`
 * business-integration settings.
 */
class StripePaymentGateway implements PaymentGateway
{
    private const CHECKOUT_URL = 'https://api.stripe.com/v1/checkout/sessions';

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(protected array $credentials = []) {}

    public function provider(): string
    {
        return 'stripe';
    }

    public function createCheckout(
        Invoice $invoice,
        float $amount,
        string $successUrl,
        string $cancelUrl,
    ): CheckoutSession {
        $secret = (string) ($this->credentials['secret_key'] ?? '');

        if ($secret === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        $currency = strtolower((string) ($invoice->currency ?? 'USD'));
        $reference = (string) ($invoice->payment_token ?? '');

        $response = Http::asForm()
            ->withToken($secret)
            ->post(self::CHECKOUT_URL, [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => $reference,
                'metadata[reference]' => $reference,
                'metadata[invoice_id]' => $invoice->id,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => $currency,
                'line_items[0][price_data][unit_amount]' => (int) round($amount * 100),
                'line_items[0][price_data][product_data][name]' => 'Invoice '.$invoice->invoice_number,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Stripe checkout failed: '.$response->body());
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Stripe returned an unexpected checkout response.');
        }

        return new CheckoutSession(
            provider: $this->provider(),
            id: is_string($data['id'] ?? null) ? $data['id'] : '',
            url: is_string($data['url'] ?? null) ? $data['url'] : '',
            amount: $amount,
            currency: strtoupper($currency),
            reference: $reference,
            completed: false,
        );
    }

    public function parseWebhook(string $payload, array $headers): ?WebhookEvent
    {
        if (! $this->verifySignature($payload, $this->signatureHeader($headers))) {
            return null;
        }

        /** @var array<string, mixed>|null $event */
        $event = json_decode($payload, true);

        if (! is_array($event)) {
            return null;
        }

        $type = (string) ($event['type'] ?? '');
        $object = $event['data']['object'] ?? [];

        if (! is_array($object)) {
            return null;
        }

        $id = (string) ($event['id'] ?? '');

        if ($type === 'checkout.session.completed') {
            $reference = $this->referenceFrom($object);
            $paid = ($object['payment_status'] ?? null) === 'paid'
                || ($object['status'] ?? null) === 'complete';

            return new WebhookEvent(
                provider: $this->provider(),
                id: $id,
                type: $type,
                paid: $paid,
                amount: ((int) ($object['amount_total'] ?? 0)) / 100,
                currency: strtoupper((string) ($object['currency'] ?? 'USD')),
                reference: $reference,
                transactionId: is_string($object['payment_intent'] ?? null) ? $object['payment_intent'] : null,
            );
        }

        if ($type === 'payment_intent.succeeded') {
            return new WebhookEvent(
                provider: $this->provider(),
                id: $id,
                type: $type,
                paid: true,
                amount: ((int) ($object['amount_received'] ?? $object['amount'] ?? 0)) / 100,
                currency: strtoupper((string) ($object['currency'] ?? 'USD')),
                reference: $this->referenceFrom($object),
                transactionId: is_string($object['id'] ?? null) ? $object['id'] : null,
            );
        }

        return new WebhookEvent(
            provider: $this->provider(),
            id: $id,
            type: $type,
            paid: false,
            amount: 0.0,
            currency: 'USD',
            reference: null,
        );
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function referenceFrom(array $object): ?string
    {
        $reference = $object['client_reference_id'] ?? null;

        if ($reference === null && isset($object['metadata']) && is_array($object['metadata'])) {
            $reference = $object['metadata']['reference'] ?? null;
        }

        return is_string($reference) && $reference !== '' ? $reference : null;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function signatureHeader(array $headers): string
    {
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'stripe-signature') {
                return $value;
            }
        }

        return '';
    }

    private function verifySignature(string $payload, string $header): bool
    {
        $secret = (string) ($this->credentials['webhook_secret'] ?? '');

        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2) {
                continue;
            }

            if ($pair[0] === 't') {
                $timestamp = (int) $pair[1];
            } elseif ($pair[0] === 'v1') {
                $signatures[] = $pair[1];
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        $tolerance = (int) config('payments.webhook_tolerance', 300);

        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
