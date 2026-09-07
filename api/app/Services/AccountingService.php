<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;

class AccountingService
{
    public function __construct(private readonly GeneralLedgerService $gl) {}

    /**
     * Post (or re-post) the journal entry that an invoice/bill produces.
     * Idempotent: any existing posted entry for the invoice is first voided.
     */
    public function postInvoice(Invoice $invoice): void
    {
        $this->gl->voidForSource($invoice);

        if (round((float) $invoice->total, 2) <= 0) {
            return;
        }

        $lines = [];
        $note = 'Invoice '.$invoice->invoice_number;

        if ($invoice->type === 'invoice') {
            $lines = [
                ['account_code' => '1100', 'debit' => (float) $invoice->total, 'description' => $note],
                ['account_code' => '4010', 'credit' => (float) $invoice->subtotal, 'description' => $note],
            ];
            if ((float) $invoice->tax_amount > 0) {
                $lines[] = ['account_code' => '2100', 'credit' => (float) $invoice->tax_amount, 'description' => $note];
            }
        } elseif ($invoice->type === 'credit_note') {
            $lines = [
                ['account_code' => '4010', 'debit' => (float) $invoice->subtotal, 'description' => 'Credit note '.$invoice->invoice_number],
                ['account_code' => '1100', 'credit' => (float) $invoice->total, 'description' => $note],
            ];
            if ((float) $invoice->tax_amount > 0) {
                $lines[] = ['account_code' => '2100', 'debit' => (float) $invoice->tax_amount, 'description' => $note];
            }
        } elseif ($invoice->type === 'bill') {
            $lines = [
                [
                    'account_code' => '5005',
                    'debit' => round((float) $invoice->subtotal + (float) $invoice->tax_amount, 2),
                    'description' => 'Bill '.$invoice->invoice_number,
                ],
                ['account_code' => '2010', 'credit' => (float) $invoice->total, 'description' => $note],
            ];
        } elseif ($invoice->type === 'debit_note') {
            $lines = [
                ['account_code' => '2010', 'debit' => (float) $invoice->total, 'description' => 'Debit note '.$invoice->invoice_number],
                [
                    'account_code' => '5005',
                    'credit' => round((float) $invoice->subtotal + (float) $invoice->tax_amount, 2),
                    'description' => $note,
                ],
            ];
        }

        $this->gl->post($invoice->company_id, $invoice->issue_date->toDateString(), $note, $lines, $invoice);
    }

    /**
     * Post (or re-post) the journal entry a completed payment produces.
     */
    public function postPayment(Payment $payment): void
    {
        $this->gl->voidForSource($payment);

        if ($payment->status !== 'completed' || round((float) $payment->amount, 2) <= 0) {
            return;
        }

        $note = 'Payment '.$payment->payment_number;

        if ($payment->type === 'receipt') {
            $lines = [
                ['account_code' => '1010', 'debit' => (float) $payment->amount, 'description' => $note],
                ['account_code' => $payment->invoice_id ? '1100' : '4030', 'credit' => (float) $payment->amount, 'description' => $note],
            ];
        } else {
            $lines = [
                ['account_code' => $payment->invoice_id ? '2010' : '5700', 'debit' => (float) $payment->amount, 'description' => $note],
                ['account_code' => '1010', 'credit' => (float) $payment->amount, 'description' => $note],
            ];
        }

        $this->gl->post($payment->company_id, $payment->payment_date->toDateString(), $note, $lines, $payment);
    }

    /**
     * Recompute an invoice's paid amount / due balance from its completed
     * payments and adjust its status accordingly.
     */
    public function refreshInvoiceFromPayments(Invoice $invoice): void
    {
        $paid = (float) $invoice->payments()->where('status', 'completed')->sum('amount');
        $total = (float) $invoice->total;

        $invoice->paid_amount = round(min($paid, $total), 2);
        $invoice->balance_due = round(max($total - $paid, 0), 2);

        if ($invoice->balance_due <= 0.01 && $total > 0) {
            $invoice->status = 'paid';
        } elseif ($invoice->paid_amount > 0) {
            $invoice->status = 'partial';
        } elseif (in_array($invoice->status, ['paid', 'partial'])) {
            $invoice->status = 'sent';
        }

        $invoice->saveQuietly();
    }
}
