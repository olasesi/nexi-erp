<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\BusinessSettingsService;
use App\Services\GeneralLedgerService;
use App\Support\PermissionGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
});

it('returns dashboard KPIs', function () {
    $contact = Contact::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'customer',
    ]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'sent',
        'subtotal' => 250,
        'tax_amount' => 0,
        'total' => 250,
        'balance_due' => 250,
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Low stock item',
        'type' => 'product',
        'stock_quantity' => 2,
        'min_stock_level' => 5,
    ]);

    $response = $this->getJson('/api/v1/dashboard?company_id='.$this->company->id)
        ->assertOk();

    expect($response->json('total_invoiced'))->toBe(250)
        ->and($response->json('outstanding_receivables'))->toBe(250)
        ->and($response->json('low_stock_products'))->toBe(1);
});

it('returns WorkDo-style financial KPIs', function () {
    Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'customer']);
    Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'both']);
    Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'supplier']);
    Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'lead']);

    Payment::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'receipt',
        'status' => 'completed',
        'amount' => 300,
    ]);

    Payment::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'payment',
        'status' => 'completed',
        'amount' => 75,
    ]);

    $gl = app(GeneralLedgerService::class);
    $gl->post($this->company->id, now()->toDateString(), 'Consulting', [
        ['account_code' => '1010', 'debit' => 500],
        ['account_code' => '4010', 'credit' => 500],
    ], $this->company);
    $gl->post($this->company->id, now()->toDateString(), 'Office rent', [
        ['account_code' => '5200', 'debit' => 120],
        ['account_code' => '1010', 'credit' => 120],
    ], $this->company);

    $response = $this->getJson('/api/v1/dashboard?company_id='.$this->company->id)
        ->assertOk();

    expect($response->json('total_clients'))->toBe(2)
        ->and($response->json('total_vendors'))->toBe(2)
        ->and($response->json('total_customer_payment'))->toBe(300)
        ->and($response->json('total_vendor_payment'))->toBe(75)
        ->and($response->json('month_revenue'))->toBe(500)
        ->and($response->json('month_expense'))->toBe(120)
        ->and($response->json('month_net_profit'))->toBe(380)
        ->and($response->json('monthly_customer_payments'))->toHaveCount(6)
        ->and($response->json('recent_revenues.0.amount'))->toBe(500)
        ->and($response->json('recent_expenses.0.amount'))->toBe(120);
});

it('defaults the card block to the current month', function () {
    $response = $this->getJson('/api/v1/dashboard?company_id='.$this->company->id)
        ->assertOk();

    expect($response->json('period.from'))->toBe(now()->startOfMonth()->toDateString())
        ->and($response->json('period.to'))->toBe(now()->toDateString());
});

it('scopes the card block to a requested date range', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));

    $contact = Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'customer']);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'sent',
        'issue_date' => '2026-06-10',
        'subtotal' => 500,
        'tax_amount' => 0,
        'total' => 500,
        'balance_due' => 500,
    ]);

    // Issued before the window, so it must stay out of the cards.
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'sent',
        'issue_date' => '2026-05-20',
        'subtotal' => 900,
        'tax_amount' => 0,
        'total' => 900,
        'balance_due' => 900,
    ]);

    $response = $this->getJson(
        '/api/v1/dashboard?company_id='.$this->company->id.'&from=2026-06-01&to=2026-06-15'
    )->assertOk();

    expect($response->json('period.from'))->toBe('2026-06-01')
        ->and($response->json('period.to'))->toBe('2026-06-15')
        ->and($response->json('total_sales'))->toBe(500)
        // The all-time invoice total keeps its own meaning.
        ->and($response->json('total_invoiced'))->toBe(1400);
});

it('reports sales and purchase returns as separate cards', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));

    $contact = Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'both']);

    foreach ([['invoice', 500], ['credit_note', 120], ['bill', 800], ['debit_note', 60]] as [$type, $total]) {
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'contact_id' => $contact->id,
            'type' => $type,
            'status' => 'sent',
            'issue_date' => '2026-06-10',
            'subtotal' => $total,
            'tax_amount' => 0,
            'total' => $total,
            'balance_due' => $total,
        ]);
    }

    $response = $this->getJson('/api/v1/dashboard?company_id='.$this->company->id)
        ->assertOk();

    expect($response->json('total_sales'))->toBe(500)
        ->and($response->json('total_sell_return'))->toBe(120)
        ->and($response->json('total_purchase'))->toBe(800)
        ->and($response->json('total_purchase_return'))->toBe(60);
});

it('excludes drafts from the card totals', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));

    $contact = Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'customer']);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'draft',
        'issue_date' => '2026-06-10',
        'subtotal' => 700,
        'tax_amount' => 0,
        'total' => 700,
        'balance_due' => 700,
    ]);

    $this->getJson('/api/v1/dashboard?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('total_sales', 0);
});

it('computes net as sales less receivables and expenses', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));

    $contact = Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'customer']);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'partial',
        'issue_date' => '2026-06-10',
        'subtotal' => 500,
        'tax_amount' => 0,
        'total' => 500,
        'balance_due' => 200,
    ]);

    app(GeneralLedgerService::class)->post($this->company->id, '2026-06-12', 'Office rent', [
        ['account_code' => '5200', 'debit' => 100],
        ['account_code' => '1010', 'credit' => 100],
    ], $this->company);

    $this->getJson('/api/v1/dashboard?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('expense', 100)
        ->assertJsonPath('net', 200);
});

it('reports today profit from posted journal entries', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));

    $gl = app(GeneralLedgerService::class);
    $gl->post($this->company->id, '2026-06-15', 'Consulting', [
        ['account_code' => '1010', 'debit' => 400],
        ['account_code' => '4010', 'credit' => 400],
    ], $this->company);
    $gl->post($this->company->id, '2026-06-15', 'Office rent', [
        ['account_code' => '5200', 'debit' => 100],
        ['account_code' => '1010', 'credit' => 100],
    ], $this->company);
    // Yesterday's profit must not leak into today.
    $gl->post($this->company->id, '2026-06-14', 'Yesterday', [
        ['account_code' => '1010', 'debit' => 900],
        ['account_code' => '4010', 'credit' => 900],
    ], $this->company);

    $this->getJson('/api/v1/dashboard?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('todays_profit', 300);
});

it('returns a gapless daily sales series with returns subtracted', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));

    $contact = Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'customer']);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'paid',
        'issue_date' => '2026-06-03',
        'subtotal' => 300,
        'tax_amount' => 0,
        'total' => 300,
        'balance_due' => 0,
    ]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'credit_note',
        'status' => 'paid',
        'issue_date' => '2026-06-05',
        'subtotal' => 100,
        'tax_amount' => 0,
        'total' => 100,
        'balance_due' => 0,
    ]);

    $series = $this->getJson('/api/v1/dashboard?company_id='.$this->company->id)
        ->assertOk()
        ->json('daily_sales');

    expect($series)->toHaveCount(15)
        ->and($series[0]['date'])->toBe('2026-06-01')
        ->and((float) $series[0]['total'])->toBe(0.0)
        ->and($series[2]['date'])->toBe('2026-06-03')
        ->and((float) $series[2]['total'])->toBe(300.0)
        ->and($series[4]['date'])->toBe('2026-06-05')
        ->and((float) $series[4]['total'])->toBe(-100.0)
        ->and($series[5]['date'])->toBe('2026-06-06')
        ->and((float) $series[5]['total'])->toBe(0.0)
        ->and($series[14]['date'])->toBe('2026-06-15');
});

it('caps the daily sales series window', function () {
    $this->getJson('/api/v1/dashboard?company_id='.$this->company->id.'&from=2020-01-01&to=2026-06-15')
        ->assertOk()
        ->assertJsonCount(366, 'daily_sales');
});

it('rejects an inverted date range', function () {
    $this->getJson('/api/v1/dashboard?company_id='.$this->company->id.'&from=2026-06-30&to=2026-06-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to');
});

it('builds a twelve month financial year sales series', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->assignRole('admin');

    $contact = Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'customer']);

    foreach ([['invoice', 300, '2026-02-10'], ['invoice', 500, '2026-04-05'], ['credit_note', 100, '2026-04-20']] as [$type, $total, $date]) {
        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'contact_id' => $contact->id,
            'type' => $type,
            'status' => 'paid',
            'issue_date' => $date,
            'subtotal' => $total,
            'tax_amount' => 0,
            'total' => $total,
            'balance_due' => 0,
        ]);
    }

    // Belongs to the previous financial year and must not appear.
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'paid',
        'issue_date' => '2025-11-10',
        'subtotal' => 900,
        'tax_amount' => 0,
        'total' => 900,
        'balance_due' => 0,
    ]);

    $response = $this->getJson('/api/v1/dashboard/financial-year-sales?company_id='.$this->company->id)
        ->assertOk();

    expect($response->json('financial_year.start_month'))->toBe(1)
        ->and($response->json('financial_year.start_date'))->toBe('2026-01-01')
        ->and($response->json('financial_year.end_date'))->toBe('2026-12-31')
        ->and($response->json('financial_year.label'))->toBe('2026')
        ->and($response->json('series'))->toHaveCount(12)
        ->and($response->json('series.1.month'))->toBe('Feb')
        ->and($response->json('series.1.sales'))->toBe(300)
        ->and($response->json('series.3.sales'))->toBe(500)
        ->and($response->json('series.3.sell_return'))->toBe(100)
        ->and($response->json('series.3.net'))->toBe(400)
        ->and($response->json('total_sales'))->toBe(800)
        ->and($response->json('total_sell_return'))->toBe(100)
        ->and($response->json('net_sales'))->toBe(700);
});

it('honours a non january financial year start month', function () {
    $this->travelTo(now()->setDate(2026, 2, 10));
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->assignRole('admin');

    app(BusinessSettingsService::class)->set($this->company->id, 'business', ['financial_year_start_month' => 4]);

    $response = $this->getJson('/api/v1/dashboard/financial-year-sales?company_id='.$this->company->id)
        ->assertOk();

    // February 2026 still belongs to the April 2025 - March 2026 year.
    expect($response->json('financial_year.start_month'))->toBe(4)
        ->and($response->json('financial_year.start_date'))->toBe('2025-04-01')
        ->and($response->json('financial_year.end_date'))->toBe('2026-03-31')
        ->and($response->json('financial_year.label'))->toBe('2025/2026');
});

it('lists sales falling due inside the payment window', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->assignRole('admin');

    $customer = Contact::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'customer',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'phone' => '+1 555 0111',
    ]);

    // Due inside the seven day window.
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $customer->id,
        'type' => 'invoice',
        'status' => 'partial',
        'issue_date' => '2026-06-01',
        'due_date' => '2026-06-20',
        'subtotal' => 400,
        'tax_amount' => 0,
        'total' => 400,
        'paid_amount' => 100,
        'balance_due' => 300,
    ]);

    // Already overdue, still surfaced with a day count.
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $customer->id,
        'type' => 'invoice',
        'status' => 'overdue',
        'issue_date' => '2026-05-01',
        'due_date' => '2026-06-10',
        'subtotal' => 250,
        'tax_amount' => 0,
        'total' => 250,
        'paid_amount' => 0,
        'balance_due' => 250,
    ]);

    // Due well beyond the window.
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $customer->id,
        'type' => 'invoice',
        'status' => 'sent',
        'issue_date' => '2026-06-01',
        'due_date' => '2026-08-01',
        'subtotal' => 999,
        'tax_amount' => 0,
        'total' => 999,
        'paid_amount' => 0,
        'balance_due' => 999,
    ]);

    // Settled, so not due at all.
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $customer->id,
        'type' => 'invoice',
        'status' => 'paid',
        'issue_date' => '2026-06-01',
        'due_date' => '2026-06-18',
        'subtotal' => 500,
        'tax_amount' => 0,
        'total' => 500,
        'paid_amount' => 500,
        'balance_due' => 0,
    ]);

    $response = $this->getJson('/api/v1/dashboard/sales-payment-due?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('window_days', 7)
        ->assertJsonPath('due_before', '2026-06-22')
        ->assertJsonPath('total', 2)
        ->assertJsonPath('total_due', 550);

    $rows = $response->json('data');

    expect($rows)->toHaveCount(2)
        // Sorted by due date, so the overdue document comes first.
        ->and($rows[0]['is_overdue'])->toBeTrue()
        ->and($rows[0]['days_overdue'])->toBe(5)
        ->and($rows[0]['contact']['name'])->toBe('Ada Lovelace')
        ->and($rows[0]['contact']['phone'])->toBe('+1 555 0111')
        ->and($rows[1]['is_overdue'])->toBeFalse()
        ->and($rows[1]['days_overdue'])->toBe(0)
        ->and($rows[1]['balance_due'])->toBe(300);
});

it('lists purchase documents falling due inside the payment window', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->assignRole('admin');

    $supplier = Contact::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'supplier',
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
    ]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $supplier->id,
        'type' => 'bill',
        'status' => 'partial',
        'issue_date' => '2026-06-01',
        'due_date' => '2026-06-18',
        'subtotal' => 800,
        'tax_amount' => 0,
        'total' => 800,
        'paid_amount' => 200,
        'balance_due' => 600,
    ]);

    // A customer invoice must never leak into the purchase feed.
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $supplier->id,
        'type' => 'invoice',
        'status' => 'partial',
        'issue_date' => '2026-06-01',
        'due_date' => '2026-06-18',
        'subtotal' => 700,
        'tax_amount' => 0,
        'total' => 700,
        'paid_amount' => 0,
        'balance_due' => 700,
    ]);

    $this->getJson('/api/v1/dashboard/purchase-payment-due?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('total_due', 600)
        ->assertJsonPath('data.0.party', 'supplier')
        ->assertJsonPath('data.0.contact.name', 'Grace Hopper')
        ->assertJsonPath('data.0.balance_due', 600);
});

it('widens the payment due window on request', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->assignRole('admin');

    $customer = Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'customer']);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $customer->id,
        'type' => 'invoice',
        'status' => 'sent',
        'issue_date' => '2026-06-01',
        'due_date' => '2026-07-10',
        'subtotal' => 120,
        'tax_amount' => 0,
        'total' => 120,
        'paid_amount' => 0,
        'balance_due' => 120,
    ]);

    $this->getJson('/api/v1/dashboard/sales-payment-due?company_id='.$this->company->id)
        ->assertJsonPath('total', 0);

    $this->getJson('/api/v1/dashboard/sales-payment-due?company_id='.$this->company->id.'&days=30')
        ->assertOk()
        ->assertJsonPath('window_days', 30)
        ->assertJsonPath('total', 1);
});

it('flags products sitting at or below their alert level', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->assignRole('admin');

    Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Healthy item',
        'type' => 'product',
        'stock_quantity' => 50,
        'min_stock_level' => 10,
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Low item',
        'sku' => 'LOW-1',
        'type' => 'product',
        'stock_quantity' => 4,
        'min_stock_level' => 10,
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Out of stock',
        'sku' => 'ZERO-1',
        'type' => 'product',
        'stock_quantity' => 0,
        'min_stock_level' => 5,
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Retired item',
        'type' => 'product',
        'is_active' => false,
        'stock_quantity' => 0,
        'min_stock_level' => 5,
    ]);

    $response = $this->getJson('/api/v1/dashboard/stock-alert?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('total', 2);

    $rows = $response->json('data');

    expect($rows)->toHaveCount(2)
        // Worst shortfall first.
        ->and($rows[0]['name'])->toBe('Out of stock')
        ->and($rows[0]['is_out_of_stock'])->toBeTrue()
        ->and($rows[0]['shortfall'])->toBe(5)
        ->and($rows[1]['name'])->toBe('Low item')
        ->and($rows[1]['sku'])->toBe('LOW-1')
        ->and($rows[1]['current_stock'])->toBe(4)
        ->and($rows[1]['alert_level'])->toBe(10)
        ->and($rows[1]['shortfall'])->toBe(6)
        ->and($rows[1]['is_out_of_stock'])->toBeFalse();
});

it('lists sales orders with remaining quantity and author', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->assignRole('admin');

    $customer = Contact::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'customer',
        'first_name' => 'Alan',
        'last_name' => 'Turing',
        'phone' => '+1 555 0122',
    ]);

    $warehouse = Warehouse::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Main Store',
    ]);

    $order = SalesOrder::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'created_by' => $this->user->id,
        'status' => 'confirmed',
        'order_date' => '2026-06-10',
        'delivery_date' => '2026-06-20',
        'total' => 500,
        'balance_due' => 500,
    ]);

    SalesOrderItem::create([
        'sales_order_id' => $order->id,
        'product_name' => 'Widget',
        'quantity' => 10,
    ]);

    // Four of the ten units are already invoiced.
    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $customer->id,
        'sales_order_id' => $order->id,
        'type' => 'invoice',
        'status' => 'sent',
        'issue_date' => '2026-06-12',
        'subtotal' => 200,
        'tax_amount' => 0,
        'total' => 200,
        'balance_due' => 200,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'description' => 'Widget',
        'quantity' => 4,
    ]);

    $response = $this->getJson('/api/v1/dashboard/sales-orders?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('total', 1);

    $row = $response->json('data.0');

    expect($response->json('data'))->toHaveCount(1)
        ->and($row['order_number'])->toBe($order->order_number)
        ->and($row['order_date'])->toBe('2026-06-10')
        ->and($row['delivery_date'])->toBe('2026-06-20')
        ->and($row['status'])->toBe('confirmed')
        ->and($row['location'])->toBe('Main Store')
        ->and($row['customer']['name'])->toBe('Alan Turing')
        ->and($row['customer']['phone'])->toBe('+1 555 0122')
        ->and($row['ordered_quantity'])->toBe(10)
        ->and($row['invoiced_quantity'])->toBe(4)
        ->and($row['quantity_remaining'])->toBe(6)
        ->and($row['added_by'])->toBe($this->user->name);
});

it('never lets remaining quantity go negative', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->assignRole('admin');

    $order = SalesOrder::factory()->create([
        'company_id' => $this->company->id,
        'order_date' => '2026-06-10',
    ]);

    SalesOrderItem::create(['sales_order_id' => $order->id, 'product_name' => 'Widget', 'quantity' => 2]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'sales_order_id' => $order->id,
        'type' => 'invoice',
        'status' => 'sent',
        'subtotal' => 300,
        'tax_amount' => 0,
        'total' => 300,
        'balance_due' => 300,
    ]);

    InvoiceItem::create(['invoice_id' => $invoice->id, 'description' => 'Widget', 'quantity' => 5]);

    $this->getJson('/api/v1/dashboard/sales-orders?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('data.0.quantity_remaining', 0);
});

it('lists sales invoices that have not been delivered yet', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->user->assignRole('admin');

    $staff = User::factory()->create(['name' => 'Delivery Driver']);
    $customer = Contact::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'customer',
        'first_name' => 'Katherine',
        'last_name' => 'Johnson',
        'phone' => '+1 555 0133',
    ]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $customer->id,
        'type' => 'invoice',
        'status' => 'sent',
        'issue_date' => '2026-06-14',
        'shipping_status' => 'shipped',
        'shipped_at' => '2026-06-14 09:00:00',
        'tracking_number' => 'TRK-1',
        'service_staff_id' => $staff->id,
        'subtotal' => 800,
        'tax_amount' => 0,
        'total' => 800,
        'balance_due' => 800,
    ]);

    // Delivered, so it drops off the pending feed.
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $customer->id,
        'type' => 'invoice',
        'status' => 'paid',
        'issue_date' => '2026-06-01',
        'shipping_status' => 'delivered',
        'shipped_at' => '2026-06-02 09:00:00',
        'subtotal' => 400,
        'tax_amount' => 0,
        'total' => 400,
        'paid_amount' => 400,
        'balance_due' => 0,
    ]);

    // A draft has not shipped but is not a pending shipment either.
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $customer->id,
        'type' => 'invoice',
        'status' => 'draft',
        'issue_date' => '2026-06-15',
        'shipping_status' => 'pending',
        'subtotal' => 100,
        'tax_amount' => 0,
        'total' => 100,
        'balance_due' => 100,
    ]);

    // Paid in full but still not delivered: payment status must not hide it,
    // because a settled invoice awaiting delivery is the urgent one.
    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $customer->id,
        'type' => 'invoice',
        'status' => 'paid',
        'issue_date' => '2026-06-13',
        'shipping_status' => 'pending',
        'subtotal' => 250,
        'tax_amount' => 0,
        'total' => 250,
        'paid_amount' => 250,
        'balance_due' => 0,
    ]);

    $response = $this->getJson('/api/v1/dashboard/pending-shipments?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('total', 2);

    $row = $response->json('data.0');
    $paidRow = $response->json('data.1');

    expect($row['shipping_status'])->toBe('shipped')
        ->and($row['issue_date'])->toBe('2026-06-14')
        ->and($row['shipped_at'])->toBe('2026-06-14')
        ->and($row['tracking_number'])->toBe('TRK-1')
        ->and($row['payment_status'])->toBe('sent')
        ->and($row['customer']['name'])->toBe('Katherine Johnson')
        ->and($row['customer']['phone'])->toBe('+1 555 0133')
        ->and($row['service_staff'])->toBe('Delivery Driver')
        ->and($paidRow['issue_date'])->toBe('2026-06-13')
        ->and($paidRow['shipping_status'])->toBe('pending')
        ->and($paidRow['payment_status'])->toBe('paid')
        ->and($paidRow['balance_due'])->toEqual(0);
});

it('keeps the dashboard card feeds behind the dashboard permission', function () {
    // No seeded permissions, so the permitted middleware skips enforcement.
    $feeds = [
        'financial-year-sales',
        'sales-payment-due',
        'purchase-payment-due',
        'stock-alert',
        'sales-orders',
        'pending-shipments',
    ];

    foreach ($feeds as $feed) {
        $this->getJson('/api/v1/dashboard/'.$feed.'?company_id='.$this->company->id)->assertOk();
    }

    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $plain = User::factory()->create();
    $plain->assignRole('user');
    Passport::actingAs($plain);

    foreach ($feeds as $feed) {
        $this->getJson('/api/v1/dashboard/'.$feed.'?company_id='.$this->company->id)->assertOk();
    }

    // Every card feed hangs off the same dashboard.view-any permission, so a
    // user who may not read the dashboard gets nothing from any of them.
    $role = Role::create(['name' => 'clerk', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo(['dashboard.view']);

    $withoutDashboard = User::factory()->create();
    $withoutDashboard->assignRole($role);
    Passport::actingAs($withoutDashboard);

    foreach ($feeds as $feed) {
        $this->getJson('/api/v1/dashboard/'.$feed.'?company_id='.$this->company->id)->assertForbidden();
    }
});
