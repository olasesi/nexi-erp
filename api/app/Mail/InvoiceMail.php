<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Invoice $invoice, public readonly string $pdfContent) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: ($this->invoice->isPayable() ? 'Bill ' : 'Invoice ').$this->invoice->invoice_number,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'mail.invoice');
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->attachmentName())
                ->withMime('application/pdf'),
        ];
    }

    protected function attachmentName(): string
    {
        return ($this->invoice->isPayable() ? 'bill-' : 'invoice-').$this->invoice->invoice_number.'.pdf';
    }
}
