<?php

namespace App\Notifications;

use App\Models\Invoice;

/**
 * Raised when an issued sales invoice passes its due date with a balance still
 * outstanding.
 */
class InvoiceOverdueNotification extends DomainNotification
{
    public function __construct(public readonly Invoice $invoice) {}

    /**
     * @return array{type: string, title: string, body: string, data: array<string, mixed>}
     */
    public function toInApp(): array
    {
        $balance = number_format((float) $this->invoice->balance_due, 2);

        return [
            'type' => 'invoice.overdue',
            'title' => 'Invoice '.$this->invoice->invoice_number.' is overdue',
            'body' => $this->invoice->currency.' '.$balance.' is past due.',
            'data' => [
                'invoice_id' => $this->invoice->id,
                'invoice_number' => $this->invoice->invoice_number,
                'balance_due' => (float) $this->invoice->balance_due,
                'due_date' => $this->invoice->due_date?->toDateString(),
            ],
        ];
    }
}
