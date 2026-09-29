<?php

use App\Mail\InvoiceMail;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    Http::fake();

    $this->company = Company::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);
    $this->contact = Contact::factory()->create(['company_id' => $this->company->id]);
    $this->product = Product::factory()->create(['company_id' => $this->company->id]);
});

function subscribeTo(string ...$events): WebhookEndpoint
{
    return WebhookEndpoint::factory()->create([
        'company_id' => null,
        'events' => $events,
    ]);
}

function createQuotation(): int
{
    $response = test()->postJson('/api/v1/quotations', [
        'company_id' => test()->company->id,
        'contact_id' => test()->contact->id,
        'warehouse_id' => test()->warehouse->id,
        'items' => [
            ['product_id' => test()->product->id, 'quantity' => 1, 'unit_price' => 50],
        ],
    ]);

    $response->assertCreated();

    return (int) $response->json('data.id');
}

it('emits quotation.accepted when a quotation is accepted', function () {
    subscribeTo('quotation.accepted');

    $quotationId = createQuotation();

    $this->postJson("/api/v1/quotations/{$quotationId}/accept?convert_to_sales_order=0")->assertOk();

    $this->assertDatabaseHas('webhook_deliveries', [
        'event' => 'quotation.accepted',
        'status' => WebhookDelivery::STATUS_SUCCEEDED,
    ]);
});

it('emits quotation.rejected when a quotation is rejected', function () {
    subscribeTo('quotation.rejected');

    $quotationId = createQuotation();

    $this->postJson("/api/v1/quotations/{$quotationId}/reject")->assertOk();

    $this->assertDatabaseHas('webhook_deliveries', [
        'event' => 'quotation.rejected',
        'status' => WebhookDelivery::STATUS_SUCCEEDED,
    ]);
});

it('emits stock_transfer.completed when a transfer is completed', function () {
    subscribeTo('stock_transfer.completed');

    $to = Warehouse::factory()->create(['company_id' => $this->company->id]);

    $response = $this->postJson('/api/v1/stock-transfers', [
        'company_id' => $this->company->id,
        'from_warehouse_id' => $this->warehouse->id,
        'to_warehouse_id' => $to->id,
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 3],
        ],
    ])->assertCreated();

    $this->postJson('/api/v1/stock-transfers/'.$response->json('data.id').'/complete')->assertOk();

    $this->assertDatabaseHas('webhook_deliveries', [
        'event' => 'stock_transfer.completed',
        'status' => WebhookDelivery::STATUS_SUCCEEDED,
    ]);
});

it('emits stock_adjustment.posted when an adjustment is created', function () {
    subscribeTo('stock_adjustment.posted');

    $this->postJson('/api/v1/stock-adjustments', [
        'company_id' => $this->company->id,
        'warehouse_id' => $this->warehouse->id,
        'type' => 'increase',
        'reason' => 'Cycle count surplus',
        'items' => [
            ['product_id' => $this->product->id, 'quantity' => 2],
        ],
    ])->assertCreated();

    $this->assertDatabaseHas('webhook_deliveries', [
        'event' => 'stock_adjustment.posted',
        'status' => WebhookDelivery::STATUS_SUCCEEDED,
    ]);
});

it('keeps the domain event and its lifecycle siblings on the same endpoint', function () {
    $endpoint = subscribeTo('quotation.accepted', 'quotation.updated');

    $quotationId = createQuotation();

    $this->postJson("/api/v1/quotations/{$quotationId}/accept?convert_to_sales_order=0")->assertOk();

    $events = WebhookDelivery::where('webhook_endpoint_id', $endpoint->getKey())
        ->distinct()
        ->pluck('event')
        ->all();

    expect($events)->toContain('quotation.accepted', 'quotation.updated');
});

it('emits payment.completed when a pending payment is completed', function () {
    subscribeTo('payment.completed');

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'type' => 'invoice',
        'subtotal' => 100,
        'tax_amount' => 0,
        'total' => 100,
        'balance_due' => 100,
    ]);

    $response = $this->postJson('/api/v1/payments', [
        'company_id' => $this->company->id,
        'type' => 'receipt',
        'contact_id' => $this->contact->id,
        'invoice_id' => $invoice->id,
        'payment_date' => '2026-09-01',
        'amount' => 100,
        'status' => 'pending',
    ])->assertCreated();

    $paymentId = (int) $response->json('data.id');

    $this->assertDatabaseMissing('webhook_deliveries', ['event' => 'payment.completed']);

    $this->postJson("/api/v1/payments/{$paymentId}/complete")->assertOk();

    $this->assertDatabaseHas('webhook_deliveries', [
        'event' => 'payment.completed',
        'status' => WebhookDelivery::STATUS_SUCCEEDED,
    ]);
});

it('emits purchase_receipt.received with the receipt and order reference', function () {
    subscribeTo('purchase_receipt.received');

    $response = $this->postJson('/api/v1/purchase-orders', [
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'confirmed',
        'items' => [
            [
                'product_id' => $this->product->id,
                'product_name' => 'Raw Widget',
                'quantity' => 4,
                'unit_price' => 5.00,
            ],
        ],
    ])->assertCreated();

    $order = PurchaseOrder::with('items')->findOrFail($response->json('data.id'));

    $this->postJson("/api/v1/purchase-orders/{$order->id}/receive", [
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'purchase_order_item_id' => $order->items->first()->id,
                'quantity_received' => 4,
            ],
        ],
    ])->assertCreated();

    $delivery = WebhookDelivery::where('event', 'purchase_receipt.received')->firstOrFail();

    expect($delivery->payload['data']['attributes'])
        ->toMatchArray([
            'purchase_order_id' => (int) $order->id,
            'receipt_number' => PurchaseReceipt::latest('id')->first()->receipt_number,
        ]);
});

it('emits invoice.sent with the recipient when the invoice is emailed', function () {
    Mail::fake();

    subscribeTo('invoice.sent');

    $contact = Contact::factory()->create([
        'company_id' => $this->company->id,
        'email' => 'billing@example.com',
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
    ]);

    $this->postJson("/api/v1/invoices/{$invoice->id}/email")->assertOk();

    $delivery = WebhookDelivery::where('event', 'invoice.sent')->firstOrFail();

    expect($delivery->payload['data']['attributes']['recipient'])->toBe('billing@example.com');

    Mail::assertSent(InvoiceMail::class);
});

it('emits reconciliation.matched when a reconciliation is completed', function () {
    subscribeTo('reconciliation.matched');

    $account = BankAccount::factory()->create(['company_id' => $this->company->id]);
    $transaction = BankTransaction::factory()->create([
        'company_id' => $this->company->id,
        'bank_account_id' => $account->id,
        'amount' => 120,
    ]);

    $response = $this->postJson('/api/v1/reconciliations', [
        'company_id' => $this->company->id,
        'bank_account_id' => $account->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'opening_balance' => 1000,
        'closing_balance' => 1120,
        'transaction_ids' => [$transaction->id],
    ])->assertCreated();

    $reconciliationId = (int) $response->json('data.id');

    $this->postJson("/api/v1/reconciliations/{$reconciliationId}/complete")->assertOk();

    $this->assertDatabaseHas('webhook_deliveries', [
        'event' => 'reconciliation.matched',
        'status' => WebhookDelivery::STATUS_SUCCEEDED,
    ]);
});
