<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; margin: 0; }
        .header { border-bottom: 3px solid #3b82f6; padding-bottom: 12px; margin-bottom: 18px; }
        .title { font-size: 22px; font-weight: bold; color: #111827; }
        .subtitle { color: #6b7280; margin-top: 2px; }
        .logo-line { display: flex; justify-content: space-between; align-items: flex-start; }
        .from { font-size: 10px; color: #374151; line-height: 1.5; }
        .from strong { color: #111827; }
        .meta { text-align: right; }
        .meta .row { margin-bottom: 3px; }
        .meta .label { color: #6b7280; display: inline-block; width: 90px; }
        .meta .value { font-weight: bold; }
        .to { margin-bottom: 16px; }
        .to .label { color: #6b7280; text-transform: uppercase; letter-spacing: 1px; font-size: 9px; margin-bottom: 3px; }
        .to .name { font-weight: bold; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; }
        th { text-align: left; background: #eff6ff; color: #1e40af; font-size: 9px; text-transform: uppercase; letter-spacing: 1px; }
        th, td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; }
        td.num, th.num { text-align: right; }
        .totals { margin-left: auto; width: 320px; margin-top: 14px; }
        .totals .row { display: flex; justify-content: space-between; padding: 4px 8px; }
        .totals .grand { font-size: 13px; font-weight: bold; background: #eff6ff; border-top: 2px solid #3b82f6; }
        .status-badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 10px; text-transform: uppercase; }
        .status-badge.paid { background: #d1fae5; color: #065f46; }
        .status-badge { background: #fef3c7; color: #92400e; }
        .footer { margin-top: 28px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; }
        .numbers { margin-top: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-line">
            <div class="from">
                <strong>{{ $invoice->company?->legal_name ?? $invoice->company?->name ?? '' }}</strong><br>
                @if ($invoice->company?->tax_id)
                    Tax ID: {{ $invoice->company->tax_id }}<br>
                @endif
                @if ($invoice->company?->email)
                    {{ $invoice->company->email }}<br>
                @endif
                @if ($invoice->company?->phone)
                    {{ $invoice->company->phone }}
                @endif
            </div>
            <div class="meta">
                <div class="title">{{ $invoice->isPayable() ? 'Bill' : 'Invoice' }}</div>
                <div class="row"><span class="label">Number</span><span class="value">{{ $invoice->invoice_number }}</span></div>
                <div class="row"><span class="label">Issued</span><span class="value">{{ $invoice->issue_date?->toDateString() }}</span></div>
                <div class="row"><span class="label">Due</span><span class="value">{{ $invoice->due_date?->toDateString() }}</span></div>
                <div class="row"><span class="label">Status</span><span class="status-badge {{ $invoice->status }}">{{ $invoice->status }}</span></div>
            </div>
        </div>
    </div>

    @if ($invoice->contact)
        <div class="to">
            <div class="label">Billed to</div>
            <div class="name">{{ $invoice->contact->full_name }}</div>
            <div>{{ $invoice->contact->email }}</div>
        </div>
    @endif

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Qty</th>
                <th class="num">Unit Price</th>
                <th class="num">Tax</th>
                <th class="num">Discount</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->tax_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->discount_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div class="row"><span>Subtotal</span><span>{{ number_format((float) $invoice->subtotal, 2) }}</span></div>
        @if ((float) $invoice->discount_amount > 0)
            <div class="row"><span>Discount</span><span>-{{ number_format((float) $invoice->discount_amount, 2) }}</span></div>
        @endif
        @if ((float) $invoice->tax_amount > 0)
            <div class="row"><span>Tax</span><span>{{ number_format((float) $invoice->tax_amount, 2) }}</span></div>
        @endif
        <div class="row grand"><span>Total</span><span>{{ $invoice->currency ?? 'USD' }} {{ number_format((float) $invoice->total, 2) }}</span></div>
        @if (! $invoice->isPayable() && (float) $invoice->paid_amount > 0)
            <div class="row"><span>Paid</span><span>-{{ number_format((float) $invoice->paid_amount, 2) }}</span></div>
            <div class="row grand"><span>Balance due</span><span>{{ $invoice->currency ?? 'USD' }} {{ number_format((float) $invoice->balance_due, 2) }}</span></div>
        @endif
    </div>

    @if ($invoice->notes)
        <div class="numbers">
            <strong>Notes</strong><br>
            {{ $invoice->notes }}
        </div>
    @endif
    @if ($invoice->terms)
        <div class="numbers">
            <strong>Terms</strong><br>
            {{ $invoice->terms }}
        </div>
    @endif

    @if ($invoice->isSales() && $invoice->payment_token)
        <div class="numbers">
            <strong>Pay online</strong><br>
            {{ url('/api/v1/public/invoices/pay/'.$invoice->payment_token) }}
        </div>
    @endif

    <div class="footer">
        Generated {{ now()->toDateTimeString() }} by Nexi ERP.
    </div>
</body>
</html>