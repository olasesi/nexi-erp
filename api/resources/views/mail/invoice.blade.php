Invoice {{ $invoice->invoice_number }}

{{ $invoice->isPayable() ? 'Bill' : 'Invoice' }} for {{ $invoice->contact?->full_name ?? 'the contact on file' }}.

Total: {{ $invoice->currency ?? 'USD' }} {{ number_format((float) $invoice->total, 2) }}
@if ($invoice->due_date)
Due: {{ $invoice->due_date->toDateString() }}
@endif

@if ($invoice->isSales() && $invoice->payment_token)
Pay online: {{ url('/api/v1/public/invoices/pay/'.$invoice->payment_token) }}
@endif