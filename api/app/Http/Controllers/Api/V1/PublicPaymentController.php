<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\PaymentGatewayManager;
use App\Services\PaymentLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPaymentController extends Controller
{
    public function __construct(
        protected PaymentGatewayManager $gateways,
        protected PaymentLinkService $links,
    ) {}

    public function show(string $token): JsonResponse
    {
        $invoice = $this->resolveInvoice($token);

        return response()->json(['data' => $this->summary($invoice)]);
    }

    /**
     * Manual pay-link settlement. Used directly by the offline stub and by
     * customers who reconcile outside a hosted checkout.
     */
    public function store(string $token, Request $request): JsonResponse
    {
        $invoice = $this->resolveInvoice($token);

        if ($error = $this->guardPayable($invoice)) {
            return $error;
        }

        $amount = $this->resolveAmount($request, $invoice);

        if ($amount instanceof JsonResponse) {
            return $amount;
        }

        $payment = $this->links->record(
            $invoice,
            $amount,
            'payment_link',
            'link-'.$invoice->invoice_number,
        );

        $invoice = Invoice::findOrFail($invoice->id);

        return response()->json([
            'data' => array_merge($this->summary($invoice), ['payment_number' => $payment->payment_number]),
        ], 201);
    }

    /**
     * Start a hosted checkout with the company's active gateway. Offline
     * gateways settle inline and return the recorded payment; hosted gateways
     * return a redirect URL and settle later via webhook.
     */
    public function checkout(string $token, Request $request): JsonResponse
    {
        $invoice = $this->resolveInvoice($token);

        if ($error = $this->guardPayable($invoice)) {
            return $error;
        }

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'success_url' => ['nullable', 'url'],
            'cancel_url' => ['nullable', 'url'],
        ]);

        $amount = $this->resolveAmount($request, $invoice);

        if ($amount instanceof JsonResponse) {
            return $amount;
        }

        $gateway = $this->gateways->driver($invoice->company_id);

        $successUrl = $validated['success_url'] ?? url("/api/v1/public/invoices/pay/{$token}");
        $cancelUrl = $validated['cancel_url'] ?? $successUrl;

        $session = $gateway->createCheckout($invoice, $amount, $successUrl, $cancelUrl);

        if (! $session->completed) {
            return response()->json(['data' => $session->toArray()], 201);
        }

        $payment = $this->links->record(
            $invoice,
            $amount,
            $gateway->provider() === 'stub' ? 'payment_link' : $gateway->provider(),
            'link-'.$invoice->invoice_number,
        );

        $invoice = Invoice::findOrFail($invoice->id);

        return response()->json([
            'data' => array_merge($session->toArray(), $this->summary($invoice), [
                'payment_number' => $payment->payment_number,
            ]),
        ], 201);
    }

    protected function resolveInvoice(string $token): Invoice
    {
        $invoice = Invoice::where('payment_token', $token)->firstOrFail();

        if (! $invoice->isSales()) {
            abort(404);
        }

        return $invoice;
    }

    /**
     * @return JsonResponse|null An error response when the invoice cannot be paid.
     */
    private function guardPayable(Invoice $invoice): ?JsonResponse
    {
        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            return response()->json(['message' => 'This invoice is not ready for payment.'], 422);
        }

        if ((float) $invoice->balance_due <= 0) {
            return response()->json(['message' => 'This invoice has no outstanding balance.'], 422);
        }

        return null;
    }

    /**
     * Resolve the requested amount, rejecting an overpayment.
     */
    private function resolveAmount(Request $request, Invoice $invoice): float|JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $amount = isset($validated['amount']) ? round((float) $validated['amount'], 2) : (float) $invoice->balance_due;

        if ($amount > (float) $invoice->balance_due + 0.005) {
            return response()->json(['message' => 'Amount exceeds the outstanding balance.'], 422);
        }

        return $amount;
    }

    /**
     * @return array<string, mixed>
     */
    protected function summary(Invoice $invoice): array
    {
        return [
            'invoice_number' => $invoice->invoice_number,
            'status' => $invoice->status,
            'contact_name' => $invoice->contact ? trim($invoice->contact->first_name.' '.$invoice->contact->last_name) : null,
            'currency' => $invoice->currency ?? 'USD',
            'issue_date' => $invoice->issue_date,
            'due_date' => $invoice->due_date,
            'subtotal' => (float) $invoice->subtotal,
            'tax_amount' => (float) $invoice->tax_amount,
            'discount_amount' => (float) $invoice->discount_amount,
            'total' => (float) $invoice->total,
            'paid_amount' => (float) $invoice->paid_amount,
            'balance_due' => (float) $invoice->balance_due,
            'items' => $invoice->items->map(fn ($item) => [
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total,
            ])->values(),
        ];
    }
}
