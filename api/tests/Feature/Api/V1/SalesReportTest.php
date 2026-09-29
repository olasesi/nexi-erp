<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\CurrencyRate;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
});

it('exposes drill-down details in the aging report', function () {
    $contact = Contact::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'customer',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'sent',
        'due_date' => now()->addDays(10)->toDateString(),
        'subtotal' => 100,
        'tax_amount' => 0,
        'total' => 100,
        'balance_due' => 100,
    ]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'overdue',
        'due_date' => now()->subDays(40)->toDateString(),
        'subtotal' => 50,
        'tax_amount' => 0,
        'total' => 50,
        'balance_due' => 50,
    ]);

    $response = $this->getJson('/api/v1/reports/aging?company_id='.$this->company->id.'&type=receivable')
        ->assertOk();

    expect($response->json('buckets.current.invoices.0.contact_name'))->toBe('Ada Lovelace')
        ->and($response->json('buckets.current.invoices.0.days_overdue'))->toBe(0)
        ->and($response->json('buckets.31_60.invoices.0.invoice_number'))->not->toBeNull()
        ->and($response->json('buckets.31_60.invoices.0.days_overdue'))->toBe(40);
});

it('reports sales by product and nets credit notes in base currency', function () {
    $product = Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Widget',
        'category_id' => null,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'status' => 'sent',
        'issue_date' => '2026-09-05',
        'currency' => 'USD',
        'subtotal' => 200,
        'tax_amount' => 20,
        'total' => 220,
        'balance_due' => 220,
    ]);
    $invoice->items()->create([
        'product_id' => $product->id,
        'description' => 'Widget',
        'quantity' => 2,
        'unit_price' => 100,
        'tax_rate' => 10,
        'tax_amount' => 20,
        'subtotal' => 200,
        'total' => 220,
    ]);

    $credit = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'credit_note',
        'status' => 'sent',
        'issue_date' => '2026-09-10',
        'currency' => 'USD',
        'subtotal' => 50,
        'tax_amount' => 5,
        'total' => 55,
        'balance_due' => 0,
    ]);
    $credit->items()->create([
        'product_id' => $product->id,
        'description' => 'Widget',
        'quantity' => 1,
        'unit_price' => 50,
        'tax_rate' => 10,
        'tax_amount' => 5,
        'subtotal' => 50,
        'total' => 55,
    ]);

    // A second product sold in EUR, converted into the USD base currency.
    CurrencyRate::create([
        'company_id' => $this->company->id,
        'base_currency' => 'USD',
        'currency' => 'EUR',
        'rate' => 2,
    ]);

    $euro = Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Gadget',
        'category_id' => null,
    ]);

    $eurInvoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'status' => 'paid',
        'issue_date' => '2026-09-15',
        'currency' => 'EUR',
        'subtotal' => 100,
        'tax_amount' => 0,
        'total' => 100,
        'balance_due' => 0,
    ]);
    $eurInvoice->items()->create([
        'product_id' => $euro->id,
        'description' => 'Gadget',
        'quantity' => 10,
        'unit_price' => 10,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'subtotal' => 100,
        'total' => 100,
    ]);

    // Non-sales documents and unpublished statuses are ignored.
    $draft = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'status' => 'draft',
        'issue_date' => '2026-09-20',
        'subtotal' => 999,
        'tax_amount' => 0,
        'total' => 999,
        'balance_due' => 999,
    ]);
    $draft->items()->create([
        'product_id' => $product->id,
        'description' => 'Widget',
        'quantity' => 9,
        'unit_price' => 111,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'subtotal' => 999,
        'total' => 999,
    ]);

    $bill = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'bill',
        'status' => 'sent',
        'issue_date' => '2026-09-25',
        'subtotal' => 5000,
        'tax_amount' => 0,
        'total' => 5000,
        'balance_due' => 5000,
    ]);
    $bill->items()->create([
        'product_id' => $product->id,
        'description' => 'Widget',
        'quantity' => 5,
        'unit_price' => 1000,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'subtotal' => 5000,
        'total' => 5000,
    ]);

    $response = $this->getJson('/api/v1/reports/sales-by-product?company_id='.$this->company->id.'&from=2026-09-01&to=2026-09-30')
        ->assertOk();

    expect($response->json('base_currency'))->toBe('USD')
        ->and($response->json('items'))->toHaveCount(2)
        ->and($response->json('items.0.product_name'))->toBe('Gadget')
        ->and($response->json('items.0.quantity'))->toBe(10)
        ->and($response->json('items.0.total'))->toBe(200)
        ->and($response->json('items.1.product_name'))->toBe('Widget')
        ->and($response->json('items.1.quantity'))->toBe(1)
        ->and($response->json('items.1.subtotal'))->toBe(150)
        ->and($response->json('items.1.tax'))->toBe(15)
        ->and($response->json('items.1.total'))->toBe(165)
        ->and($response->json('total_quantity'))->toBe(11)
        ->and($response->json('total_revenue'))->toBe(365);
});

it('groups sales by category with an uncategorized bucket', function () {
    $category = ProductCategory::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Hardware',
    ]);

    $categorised = Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Screw',
        'category_id' => $category->id,
    ]);

    $unbranded = Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Loose part',
        'category_id' => null,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'status' => 'sent',
        'issue_date' => '2026-09-05',
        'currency' => 'USD',
        'subtotal' => 180,
        'tax_amount' => 10,
        'total' => 190,
        'balance_due' => 190,
    ]);
    $invoice->items()->create([
        'product_id' => $categorised->id,
        'description' => 'Screw',
        'quantity' => 10,
        'unit_price' => 10,
        'tax_rate' => 10,
        'tax_amount' => 10,
        'subtotal' => 100,
        'total' => 110,
    ]);
    $invoice->items()->create([
        'product_id' => $unbranded->id,
        'description' => 'Loose part',
        'quantity' => 5,
        'unit_price' => 10,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'subtotal' => 50,
        'total' => 50,
    ]);
    $invoice->items()->create([
        'product_id' => null,
        'description' => 'Ad-hoc service',
        'quantity' => 1,
        'unit_price' => 30,
        'tax_rate' => 0,
        'tax_amount' => 0,
        'subtotal' => 30,
        'total' => 30,
    ]);

    $response = $this->getJson('/api/v1/reports/sales-by-category?company_id='.$this->company->id.'&from=2026-09-01&to=2026-09-30')
        ->assertOk();

    expect($response->json('categories'))->toHaveCount(2)
        ->and($response->json('categories.0.category_name'))->toBe('Hardware')
        ->and($response->json('categories.0.quantity'))->toBe(10)
        ->and($response->json('categories.0.total'))->toBe(110)
        ->and($response->json('categories.1.category_name'))->toBe('Uncategorized')
        ->and($response->json('categories.1.quantity'))->toBe(6)
        ->and($response->json('categories.1.total'))->toBe(80)
        ->and($response->json('total_revenue'))->toBe(190);
});

it('summarises VAT per rate and nets credit notes', function () {
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'status' => 'sent',
        'issue_date' => '2026-09-05',
        'currency' => 'USD',
        'subtotal' => 150,
        'tax_amount' => 20,
        'total' => 170,
        'balance_due' => 170,
    ]);
    $invoice->items()->create([
        'product_id' => null,
        'description' => 'Standard rate',
        'quantity' => 1,
        'unit_price' => 100,
        'tax_rate' => 10,
        'tax_amount' => 10,
        'subtotal' => 100,
        'total' => 110,
    ]);
    $invoice->items()->create([
        'product_id' => null,
        'description' => 'Premium rate',
        'quantity' => 1,
        'unit_price' => 50,
        'tax_rate' => 20,
        'tax_amount' => 10,
        'subtotal' => 50,
        'total' => 60,
    ]);

    $credit = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'credit_note',
        'status' => 'sent',
        'issue_date' => '2026-09-12',
        'currency' => 'USD',
        'subtotal' => 20,
        'tax_amount' => 2,
        'total' => 22,
        'balance_due' => 0,
    ]);
    $credit->items()->create([
        'product_id' => null,
        'description' => 'Standard refund',
        'quantity' => 1,
        'unit_price' => 20,
        'tax_rate' => 10,
        'tax_amount' => 2,
        'subtotal' => 20,
        'total' => 22,
    ]);

    $response = $this->getJson('/api/v1/reports/vat-summary?company_id='.$this->company->id.'&from=2026-09-01&to=2026-09-30')
        ->assertOk();

    expect($response->json('rates'))->toHaveCount(2)
        ->and($response->json('rates.0.tax_rate'))->toBe(10)
        ->and($response->json('rates.0.net_amount'))->toBe(80)
        ->and($response->json('rates.0.tax_amount'))->toBe(8)
        ->and($response->json('rates.0.gross_amount'))->toBe(88)
        ->and($response->json('rates.0.documents'))->toBe(2)
        ->and($response->json('rates.1.tax_rate'))->toBe(20)
        ->and($response->json('rates.1.tax_amount'))->toBe(10)
        ->and($response->json('total_net'))->toBe(130)
        ->and($response->json('total_tax'))->toBe(18)
        ->and($response->json('total_gross'))->toBe(148);
});
