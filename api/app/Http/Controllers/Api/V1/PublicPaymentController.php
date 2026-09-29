<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicPaymentController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $invoice = $this->resolveInvoice($token);

        return response()->json(['data' => $this->summary($invoice)]);
    }

    public function store(string $token, Request $request): JsonResponse
    {
        $invoice = $this->resolveInvoice($token);

        if (in_array($invoice->status, ['draft', 'cancelled'])) {
            return response()->json(['message' => 'This invoice is not ready for payment.'], 422);
        }

        if ((float) $invoice->balance_due <= 0) {
            return response()->json(['message' => 'This invoice has no outstanding balance.'], 422);
        }

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $amount = isset($validated['amount']) ? round((float) $validated['amount'], 2) : (float) $invoice->balance_due;

        if ($amount > (float) $invoice->balance_due + 0.005) {
            return response()->json(['message' => 'Amount exceeds the outstanding balance.'], 422);
        }

        $payment = DB::transaction(function () use ($invoice, $amount) {
            $payment = Payment::create([
                'company_id' => $invoice->company_id,
                'payment_number' => $this->nextPaymentNumber(),
                'type' => 'receipt',
                'contact_id' => $invoice->contact_id,
                'invoice_id' => $invoice->id,
                'payment_date' => now()->toDateString(),
                'amount' => $amount,
                'method' => 'payment_link',
                'status' => 'completed',
                'reference' => 'link-'.$invoice->invoice_number,
            ]);

            app(AccountingService::class)->postPayment($payment);
            app(AccountingService::class)->refreshInvoiceFromPayments($invoice);

            return $payment;
        });

        $invoice = Invoice::findOrFail($invoice->id);

        return response()->json([
            'data' => array_merge($this->summary($invoice), ['payment_number' => $payment->payment_number]),
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

    protected function nextPaymentNumber(): string
    {
        return 'REC-'.now()->format('Ymd').'-'.str_pad((string) (Payment::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }
}
