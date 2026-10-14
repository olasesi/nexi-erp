<?php

namespace App\Notifications;

use App\Models\Payment;

/**
 * Raised when a payment is recorded against an invoice.
 */
class PaymentReceivedNotification extends DomainNotification
{
    public function __construct(public readonly Payment $payment) {}

    /**
     * @return array{type: string, title: string, body: string, data: array<string, mixed>}
     */
    public function toInApp(): array
    {
        $amount = number_format((float) $this->payment->amount, 2);

        return [
            'type' => 'payment.received',
            'title' => 'Payment '.$this->payment->payment_number.' received',
            'body' => $amount.' recorded against invoice #'.$this->payment->invoice_id.'.',
            'data' => [
                'payment_id' => $this->payment->id,
                'payment_number' => $this->payment->payment_number,
                'invoice_id' => $this->payment->invoice_id,
                'amount' => (float) $this->payment->amount,
            ],
        ];
    }
}
