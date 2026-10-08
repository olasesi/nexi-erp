<?php

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Contact;
use App\Models\CrmStage;
use App\Models\Deal;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\GeneralLedgerService;
use App\Support\PermissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);

    $this->user->givePermissionTo(
        Permission::firstOrCreate([
            'name' => 'analytics.view',
            'guard_name' => PermissionGuard::name(),
        ])
    );

    $this->user->givePermissionTo(
        Permission::firstOrCreate([
            'name' => 'addons.update',
            'guard_name' => PermissionGuard::name(),
        ])
    );
});

/**
 * One company's worth of Smart Dashboard data: journalised revenue and
 * expense, a customer and a vendor, stock at three levels, sales and
 * purchase documents, and a small CRM pipeline.
 *
 * @return array<string, mixed>
 */
function seedSmartDashboard(Company $company): array
{
    $gl = app(GeneralLedgerService::class);

    $gl->post($company->id, now()->toDateString(), 'October sale', [
        ['account_code' => '1010', 'debit' => 1000],
        ['account_code' => '4010', 'credit' => 1000],
    ], $company);

    $gl->post($company->id, now()->toDateString(), 'Office rent', [
        ['account_code' => '5200', 'debit' => 300],
        ['account_code' => '1010', 'credit' => 300],
    ], $company);

    $customer = Contact::factory()->create(['company_id' => $company->id, 'type' => 'customer']);
    $vendor = Contact::factory()->create(['company_id' => $company->id, 'type' => 'supplier']);
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $category = ProductCategory::factory()->create(['company_id' => $company->id]);

    $stocked = Product::factory()->create([
        'company_id' => $company->id,
        'type' => 'product',
        'category_id' => $category->id,
        'stock_quantity' => 10,
        'min_stock_level' => 5,
        'cost_price' => 2,
        'sale_price' => 5,
    ]);

    $low = Product::factory()->create([
        'company_id' => $company->id,
        'type' => 'product',
        'category_id' => null,
        'stock_quantity' => 2,
        'min_stock_level' => 5,
        'cost_price' => 1.5,
        'sale_price' => 4,
    ]);

    $empty = Product::factory()->create([
        'company_id' => $company->id,
        'type' => 'product',
        'category_id' => null,
        'stock_quantity' => 0,
        'min_stock_level' => 5,
        'cost_price' => 1,
        'sale_price' => 3,
    ]);

    Inventory::create([
        'product_id' => $stocked->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 7,
    ]);

    Invoice::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $customer->id,
        'type' => 'invoice',
        'status' => 'sent',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(10)->toDateString(),
        'subtotal' => 1000,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 1000,
        'paid_amount' => 0,
        'balance_due' => 1000,
    ]);

    Invoice::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $vendor->id,
        'type' => 'bill',
        'status' => 'sent',
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(10)->toDateString(),
        'subtotal' => 400,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total' => 400,
        'paid_amount' => 0,
        'balance_due' => 400,
    ]);

    Quotation::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'status' => 'sent',
    ]);

    SalesOrder::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'status' => 'confirmed',
    ]);

    PurchaseOrder::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $vendor->id,
        'warehouse_id' => $warehouse->id,
        'status' => 'confirmed',
        'total' => 750,
    ]);

    $stage = CrmStage::factory()->create(['company_id' => $company->id]);

    Deal::factory()->create([
        'company_id' => $company->id,
        'crm_stage_id' => $stage->id,
        'value' => 5000,
        'status' => Deal::STATUS_OPEN,
    ]);

    Deal::factory()->create([
        'company_id' => $company->id,
        'crm_stage_id' => $stage->id,
        'value' => 8000,
        'status' => Deal::STATUS_WON,
    ]);

    Deal::factory()->create([
        'company_id' => $company->id,
        'crm_stage_id' => $stage->id,
        'value' => 2000,
        'status' => Deal::STATUS_LOST,
    ]);

    Lead::factory()->create([
        'company_id' => $company->id,
        'source' => 'website',
        'status' => Lead::STATUS_NEW,
        'next_follow_up_at' => now()->addWeek()->toDateString(),
    ]);

    Lead::factory()->create([
        'company_id' => $company->id,
        'source' => 'referral',
        'status' => Lead::STATUS_CONVERTED,
        'converted_at' => now(),
        'next_follow_up_at' => null,
    ]);

    BankAccount::factory()->create(['company_id' => $company->id, 'opening_balance' => 5000]);

    return [
        'customer' => $customer,
        'vendor' => $vendor,
        'warehouse' => $warehouse,
        'category' => $category,
        'stocked' => $stocked,
        'low' => $low,
        'empty' => $empty,
    ];
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/analytics/executive?company_id='.$this->company->id)
        ->assertUnauthorized();
});

it('blocks a user without the analytics permission', function () {
    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    foreach (['executive', 'financial', 'sales', 'operations'] as $tab) {
        $this->getJson("/api/v1/analytics/{$tab}?company_id={$this->company->id}")
            ->assertForbidden();
    }
});

it('hides the tabs behind the smart-dashboard add-on toggle', function () {
    $this->putJson('/api/v1/addons/smart-dashboard', ['enabled' => false])->assertOk();

    foreach (['executive', 'financial', 'sales', 'operations'] as $tab) {
        $this->getJson("/api/v1/analytics/{$tab}?company_id={$this->company->id}")
            ->assertForbidden()
            ->assertJsonPath('code', 'addon_disabled');
    }
});

it('validates the tab request', function () {
    $base = '/api/v1/analytics/executive';
    $company = $this->company->id;

    $this->getJson($base)->assertUnprocessable();
    $this->getJson($base.'?company_id=999999')->assertUnprocessable();
    $this->getJson("{$base}?company_id={$company}&limit=0")->assertUnprocessable();
    $this->getJson("{$base}?company_id={$company}&limit=101")->assertUnprocessable();
    $this->getJson("{$base}?company_id={$company}&fresh=nope")->assertUnprocessable();
});

it('returns the executive tab payload', function () {
    $seed = seedSmartDashboard($this->company);

    $response = $this->getJson('/api/v1/analytics/executive?company_id='.$this->company->id)
        ->assertOk();

    expect($response->json('tab'))->toBe('executive')
        ->and($response->json('company_id'))->toBe($this->company->id)
        ->and($response->json('period'))->toBe([
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
        ])
        ->and($response->json('cached'))->toBeFalse()
        ->and($response->json('generated_at'))->not->toBeNull();

    expect($response->json('kpis.monthly_revenue.value'))->toEqual(1000.0)
        ->and($response->json('kpis.monthly_revenue.previous'))->toEqual(0.0)
        ->and($response->json('kpis.monthly_revenue.change_pct'))->toEqual(100.0)
        ->and($response->json('kpis.monthly_expense.value'))->toEqual(300.0)
        ->and($response->json('kpis.monthly_expense.change_pct'))->toEqual(100.0)
        ->and($response->json('kpis.monthly_net_profit.value'))->toEqual(700.0)
        ->and($response->json('kpis.monthly_net_profit.margin_pct'))->toEqual(70.0)
        ->and($response->json('kpis.sales_pipeline.value'))->toEqual(5000.0)
        ->and($response->json('kpis.sales_pipeline.active_leads'))->toBe(1)
        ->and($response->json('kpis.sales_pipeline.open_deals'))->toBe(1)
        ->and($response->json('kpis.sales_pipeline.conversion_rate'))->toEqual(50.0)
        ->and($response->json('kpis.customers'))->toBe(1)
        ->and($response->json('kpis.products'))->toBe(3);

    $health = $response->json('health');

    expect(array_keys($health['categories']))
        ->toBe(['financial', 'sales', 'operations', 'team', 'projects'])
        ->and($health['overall_score'])->toBeInt()
        ->and($health['coverage']['scored'])->toBe(4)
        ->and($health['generated_by'])->toBe('rules');

    $insightKeys = collect($response->json('insights'))->pluck('key');

    expect($insightKeys->first())->toBe('strong_conversion')
        ->and($insightKeys->all())->toContain('stock_out', 'stock_low');

    expect($response->json('module_summaries.crm'))->toEqual([
        'active_leads' => 1,
        'conversion_rate' => 50.0,
        'pipeline_value' => 5000.0,
    ])
        ->and($response->json('module_summaries.account.cash_balance'))->toEqual(5000.0)
        ->and($response->json('module_summaries.account.pending_invoices'))->toBe(1)
        ->and($response->json('module_summaries.account.pending_bills'))->toBe(1)
        ->and($response->json('module_summaries.sales.monthly_revenue'))->toEqual(1000.0)
        ->and($response->json('module_summaries.sales.quotations'))->toBe(1)
        ->and($response->json('module_summaries.sales.orders'))->toBe(1)
        ->and($response->json('module_summaries.inventory.low_stock'))->toBe(2)
        ->and($response->json('module_summaries.inventory.out_of_stock'))->toBe(1)
        ->and($response->json('module_summaries.inventory.total_stock_value'))->toEqual(23.0);

    expect($response->json('top_customers'))->toHaveCount(1)
        ->and($response->json('top_customers.0.name'))->toBe($seed['customer']->full_name)
        ->and($response->json('top_customers.0.total'))->toEqual(1000.0)
        ->and($response->json('top_customers.0.invoices'))->toBe(1);

    expect($response->json('recent_transactions'))->toHaveCount(1)
        ->and($response->json('recent_transactions.0.description'))->toBe('October sale')
        ->and($response->json('recent_transactions.0.entry_date'))->toBe(now()->toDateString())
        ->and($response->json('recent_transactions.0.amount'))->toEqual(1000.0)
        ->and($response->json('recent_transactions.0.accounts'))->not->toBeEmpty();
});

it('returns the financial tab payload', function () {
    seedSmartDashboard($this->company);

    $response = $this->getJson('/api/v1/analytics/financial?company_id='.$this->company->id)
        ->assertOk();

    $expectedAvg = round(1000 / (now()->startOfMonth()->diffInDays(now()->startOfDay()) + 1), 2);

    expect($response->json('revenue.total'))->toEqual(1000.0)
        ->and($response->json('revenue.previous_total'))->toEqual(0.0)
        ->and($response->json('revenue.growth_pct'))->toEqual(100.0)
        ->and($response->json('revenue.avg_daily'))->toEqual($expectedAvg)
        ->and($response->json('expenses.total'))->toEqual(300.0)
        ->and($response->json('expenses.previous_total'))->toEqual(0.0)
        ->and($response->json('expenses.growth_pct'))->toEqual(100.0)
        ->and($response->json('profitability.net'))->toEqual(700.0)
        ->and($response->json('profitability.margin_pct'))->toEqual(70.0)
        ->and($response->json('cash_flow.inflow'))->toEqual(1000.0)
        ->and($response->json('cash_flow.outflow'))->toEqual(300.0)
        ->and($response->json('cash_flow.net'))->toEqual(700.0);

    expect($response->json('revenue_by_source'))->toHaveCount(1)
        ->and($response->json('revenue_by_source.0.code'))->toBe('4010')
        ->and($response->json('revenue_by_source.0.balance'))->toEqual(1000.0);

    $trend = $response->json('trend');

    expect($trend)->toHaveCount(12)
        ->and($trend[11]['month'])->toBe(now()->format('Y-m'))
        ->and($trend[11]['revenue'])->toEqual(1000.0)
        ->and($trend[11]['expenses'])->toEqual(300.0)
        ->and($trend[11]['net'])->toEqual(700.0);

    expect($response->json('transactions.total'))->toBe(1)
        ->and($response->json('transactions.limit'))->toBe(25)
        ->and($response->json('transactions.data'))->toHaveCount(1)
        ->and($response->json('transactions.data.0.amount'))->toEqual(1000.0);
});

it('returns the sales tab payload', function () {
    seedSmartDashboard($this->company);

    $response = $this->getJson('/api/v1/analytics/sales?company_id='.$this->company->id)
        ->assertOk();

    expect($response->json('kpis.active_leads'))->toBe(1)
        ->and($response->json('kpis.pipeline_value'))->toEqual(5000.0)
        ->and($response->json('kpis.conversion_rate'))->toEqual(50.0)
        ->and($response->json('kpis.active_deals'))->toBe(1)
        ->and($response->json('kpis.quotations'))->toBe(1)
        ->and($response->json('kpis.sales_orders'))->toBe(1)
        ->and($response->json('kpis.invoices'))->toBe(1);

    expect($response->json('lead_funnel'))->toHaveCount(5)
        ->and($response->json('lead_funnel.0'))->toBe(['status' => 'new', 'count' => 1])
        ->and($response->json('lead_funnel.3'))->toBe(['status' => 'converted', 'count' => 1]);

    expect($response->json('leads_by_source'))->toHaveCount(7)
        ->and($response->json('leads_by_source.0'))->toEqual([
            'source' => 'referral',
            'count' => 1,
            'converted' => 1,
            'conversion_rate' => 100.0,
        ])
        ->and($response->json('leads_by_source.1'))->toEqual([
            'source' => 'website',
            'count' => 1,
            'converted' => 0,
            'conversion_rate' => 0.0,
        ]);

    $monthly = $response->json('monthly_leads');

    expect($monthly)->toHaveCount(12)
        ->and($monthly[11]['month'])->toBe(now()->format('Y-m'))
        ->and($monthly[11]['total'])->toBe(2)
        ->and($monthly[11]['converted'])->toBe(1);

    $vsPurchase = $response->json('sales_vs_purchase');

    expect($vsPurchase)->toHaveCount(12)
        ->and($vsPurchase[11]['month'])->toBe(now()->format('Y-m'))
        ->and($vsPurchase[11]['sales'])->toEqual(1000.0)
        ->and($vsPurchase[11]['purchases'])->toEqual(400.0)
        ->and($vsPurchase[0]['sales'])->toEqual(0.0);
});

it('returns the operations tab payload', function () {
    $seed = seedSmartDashboard($this->company);

    $response = $this->getJson('/api/v1/analytics/operations?company_id='.$this->company->id)
        ->assertOk();

    expect($response->json('kpis.total_products'))->toBe(3)
        ->and($response->json('kpis.stock_value'))->toEqual(23.0)
        ->and($response->json('kpis.out_of_stock'))->toBe(1)
        ->and($response->json('kpis.low_stock'))->toBe(2)
        ->and($response->json('kpis.active_warehouses'))->toBe(1);

    $categories = collect($response->json('stock_by_category'));

    expect($categories)->toHaveCount(2)
        ->and($categories->firstWhere('category_id', $seed['category']->id))
        ->toMatchArray(['products' => 1, 'quantity' => 10, 'value' => 20])
        ->and($categories->firstWhere('category', 'Uncategorised'))
        ->toMatchArray(['category_id' => null, 'products' => 2, 'quantity' => 2, 'value' => 3]);

    expect($response->json('stock_by_warehouse'))->toHaveCount(1)
        ->and($response->json('stock_by_warehouse.0.warehouse'))->toBe($seed['warehouse']->name)
        ->and($response->json('stock_by_warehouse.0.quantity'))->toEqual(7.0)
        ->and($response->json('stock_by_warehouse.0.value'))->toEqual(14.0);

    expect($response->json('purchases.open_orders'))->toBe(1)
        ->and($response->json('purchases.order_value'))->toEqual(750.0)
        ->and($response->json('purchases.pending_receipts'))->toBe(0)
        ->and($response->json('purchases.top_vendors'))->toHaveCount(1)
        ->and($response->json('purchases.top_vendors.0.vendor'))->toBe($seed['vendor']->full_name)
        ->and($response->json('purchases.top_vendors.0.orders'))->toBe(1)
        ->and($response->json('purchases.top_vendors.0.value'))->toEqual(750.0);

    expect($response->json('products.total'))->toBe(3)
        ->and($response->json('products.limit'))->toBe(25)
        ->and($response->json('products.data'))->toHaveCount(3);

    $products = collect($response->json('products.data'));

    expect($products->firstWhere('product_id', $seed['stocked']->id))
        ->toMatchArray([
            'status' => 'in_stock',
            'stock_quantity' => 10,
            'min_stock_level' => 5,
            'stock_value' => 20,
            'category' => $seed['category']->name,
        ])
        ->and($products->firstWhere('product_id', $seed['low']->id)['status'])->toBe('low_stock')
        ->and($products->firstWhere('product_id', $seed['empty']->id)['status'])->toBe('out_of_stock');
});

it('scopes every tab to the requested company', function () {
    seedSmartDashboard($this->company);

    // The CompanyScoped global scope pins every read to the acting user's
    // company, so the rival company's data is seeded unauthenticated and
    // the acting user is restored before the requests.
    $other = Company::factory()->create();

    $this->app['auth']->forgetGuards();
    seedSmartDashboard($other);
    Passport::actingAs($this->user);

    $executive = $this->getJson('/api/v1/analytics/executive?company_id='.$this->company->id)
        ->assertOk();
    $financial = $this->getJson('/api/v1/analytics/financial?company_id='.$this->company->id)
        ->assertOk();
    $sales = $this->getJson('/api/v1/analytics/sales?company_id='.$this->company->id)
        ->assertOk();
    $operations = $this->getJson('/api/v1/analytics/operations?company_id='.$this->company->id)
        ->assertOk();

    expect($executive->json('kpis.monthly_revenue.value'))->toEqual(1000.0)
        ->and($executive->json('top_customers'))->toHaveCount(1)
        ->and($executive->json('top_customers.0.total'))->toEqual(1000.0)
        ->and($financial->json('revenue.total'))->toEqual(1000.0)
        ->and($financial->json('transactions.total'))->toBe(1)
        ->and($sales->json('kpis.quotations'))->toBe(1)
        ->and($sales->json('kpis.invoices'))->toBe(1)
        ->and($operations->json('kpis.total_products'))->toBe(3)
        ->and($operations->json('products.total'))->toBe(3);
});

it('honours the limit parameter', function () {
    seedSmartDashboard($this->company);

    $response = $this->getJson('/api/v1/analytics/operations?company_id='.$this->company->id.'&limit=2')
        ->assertOk();

    expect($response->json('products.total'))->toBe(3)
        ->and($response->json('products.limit'))->toBe(2)
        ->and($response->json('products.data'))->toHaveCount(2);
});

it('serves cached payloads, honours fresh and records the metrics', function () {
    config(['analytics.cache_ttl' => 300]);

    $first = $this->getJson('/api/v1/analytics/executive?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('cached', false);

    $second = $this->getJson('/api/v1/analytics/executive?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('cached', true);

    expect($second->json('generated_at'))->toBe($first->json('generated_at'))
        ->and($second->json('tab'))->toBe('executive');

    $this->getJson('/api/v1/analytics/executive?company_id='.$this->company->id.'&fresh=1')
        ->assertOk()
        ->assertJsonPath('cached', false);

    $this->get('/api/metrics')
        ->assertOk()
        ->assertSee('nexi_erp_analytics_requests_total{tab="executive"} 3', false)
        ->assertSee('nexi_erp_analytics_cache_hits_total{tab="executive"} 1', false)
        ->assertSee('nexi_erp_analytics_build_duration_seconds_count{tab="executive"} 2', false);
});
