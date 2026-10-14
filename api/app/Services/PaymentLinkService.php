<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Records a payment against a sales invoice and keeps the ledger and the
 * invoice's paid/balance state in step. Shared by the manual pay-link endpoint
 * and the gateway webhook receiver so both paths post identically.
 */
class PaymentLinkService
{
    /**
     * Payment methods allowed by the payments table, plus aliases that map a
     * gateway provider onto the closest stored method.
     */
    private const METHODS = ['cash', 'bank_transfer', 'credit_card', 'check', 'other', 'payment_link'];

    private const METHOD_ALIASES = [
        'stripe' => 'credit_card',
        'stub' => 'payment_link',
    ];

    public function __construct(
        protected AccountingService $accounting
    ) {}

    public function record(Invoice $invoice, float $amount, string $method, string $reference): Payment
    {
        $method = $this->normaliseMethod($method);

        return DB::transaction(function () use ($invoice, $amount, $method, $reference): Payment {
            $payment = Payment::create([
                'company_id' => $invoice->company_id,
                'payment_number' => $this->nextPaymentNumber(),
                'type' => 'receipt',
                'contact_id' => $invoice->contact_id,
                'invoice_id' => $invoice->id,
                'payment_date' => now()->toDateString(),
                'amount' => round($amount, 2),
                'method' => $method,
                'status' => 'completed',
                'reference' => $reference,
            ]);

            $this->accounting->postPayment($payment);
            $this->accounting->refreshInvoiceFromPayments($invoice);

            return $payment;
        });
    }

    /**
     * Guard against replaying the same gateway event (idempotent webhooks).
     */
    public function alreadyRecorded(Invoice $invoice, string $reference): bool
    {
        return Payment::query()
            ->withoutGlobalScope('company_scope')
            ->where('invoice_id', $invoice->id)
            ->where('reference', $reference)
            ->exists();
    }

    private function nextPaymentNumber(): string
    {
        return 'REC-'.now()->format('Ymd').'-'.str_pad((string) (Payment::max('id') + 1), 5, '0', STR_PAD_LEFT);
    }

    /**
     * Normalise an inbound gateway key (e.g. "stripe") to a stored payment
     * method the payments table accepts.
     */
    private function normaliseMethod(string $method): string
    {
        $resolved = self::METHOD_ALIASES[$method] ?? $method;

        return in_array($resolved, self::METHODS, true) ? $resolved : 'other';
    }
}
