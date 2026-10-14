<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Payments\WebhookEvent;
use App\Services\PaymentGatewayManager;
use App\Services\PaymentLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Signature-verified receiver for payment-gateway callbacks. The payload is
 * parsed by the provider's driver; on a verified paid event the matching
 * invoice's outstanding balance is settled and posted to the ledger. Repeated
 * deliveries are ignored, so gateways may safely retry.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
        protected PaymentLinkService $links,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        if (! $this->gateways->supports($provider)) {
            return response()->json(['message' => "Unknown payment provider [{$provider}]."], 404);
        }

        $payload = $request->getContent();

        // Gateways sign with a per-company secret, so identify the company from
        // the (unverified) reference before building the driver and verifying.
        $gateway = $this->gateways->make($provider, $this->companyIdFor($payload));

        $event = $gateway->parseWebhook($payload, $this->headers($request));

        if ($event === null) {
            return response()->json(['message' => 'Invalid webhook signature or payload.'], 400);
        }

        if (! $event->paid || $event->reference === null) {
            return response()->json(['message' => 'Webhook received.', 'handled' => false]);
        }

        $invoice = Invoice::where('payment_token', $event->reference)->first();

        if ($invoice === null || ! $invoice->isSales()) {
            return response()->json(['message' => 'Webhook received.', 'handled' => false]);
        }

        if ((float) $invoice->balance_due <= 0) {
            return response()->json(['message' => 'Invoice already settled.', 'handled' => true]);
        }

        $reference = $this->reference($event);

        if ($this->links->alreadyRecorded($invoice, $reference)) {
            return response()->json(['message' => 'Webhook already processed.', 'handled' => true]);
        }

        $amount = $event->amount > 0
            ? min($event->amount, (float) $invoice->balance_due)
            : (float) $invoice->balance_due;

        $payment = $this->links->record($invoice, $amount, $provider, $reference);

        return response()->json([
            'message' => 'Webhook processed.',
            'handled' => true,
            'payment_number' => $payment->payment_number,
        ]);
    }

    private function reference(WebhookEvent $event): string
    {
        return $event->transactionId ?? $event->id;
    }

    /**
     * Best-effort company lookup from the raw reference, used only to load the
     * right signing secret. Never trusted for anything until the signature is
     * verified afterwards.
     */
    private function companyIdFor(string $payload): ?int
    {
        /** @var array<string, mixed>|null $data */
        $data = json_decode($payload, true);

        if (! is_array($data)) {
            return null;
        }

        $object = $data['data']['object'] ?? null;

        if (! is_array($object)) {
            return null;
        }

        $reference = $object['client_reference_id'] ?? null;

        if ($reference === null && isset($object['metadata']) && is_array($object['metadata'])) {
            $reference = $object['metadata']['reference'] ?? null;
        }

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        $companyId = Invoice::query()
            ->withoutGlobalScope('company_scope')
            ->where('payment_token', $reference)
            ->value('company_id');

        return is_numeric($companyId) ? (int) $companyId : null;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $headers = [];

        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = $values[0] ?? '';
        }

        return $headers;
    }
}
