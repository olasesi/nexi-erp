<?php

use App\Models\Company;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\PosPayment;
use App\Models\PosRegister;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Support\PermissionGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);
});

function posOrderPayload(array $overrides = []): array
{
    return array_merge([
        'items' => [
            ['product_name' => 'Coffee', 'quantity' => 2, 'unit_price' => 5.00],
            ['product_name' => 'Cake', 'quantity' => 1, 'unit_price' => 10.00, 'tax_rate' => 10],
        ],
    ], $overrides);
}

it('creates a POS order with computed totals, a payment and the cashier', function () {
    $response = $this->postJson('/api/v1/pos-orders', posOrderPayload([
        'payments' => [
            ['method' => 'cash', 'amount' => 21],
        ],
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.subtotal', 20)
        ->assertJsonPath('data.tax_amount', 1)
        ->assertJsonPath('data.discount_amount', 0)
        ->assertJsonPath('data.total', 21)
        ->assertJsonPath('data.paid_amount', 21)
        ->assertJsonPath('data.balance_due', 0)
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.cashier_id', $this->user->id)
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonStructure(['data' => ['order_number', 'items', 'payments']]);

    $order = PosOrder::first();

    expect(str_starts_with($order->order_number, 'POS-'))->toBeTrue()
        ->and($order->cashier_id)->toBe($this->user->id)
        ->and($order->items)->toHaveCount(2)
        ->and((float) $order->payments()->where('method', 'cash')->sum('amount'))->toBe(21.0);
});

it('defaults the order to completed when no status is sent', function () {
    $this->postJson('/api/v1/pos-orders', posOrderPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.payment_status', 'pending')
        ->assertJsonPath('data.balance_due', 21);
});

it('applies a document wide discount on top of the line totals', function () {
    $this->postJson('/api/v1/pos-orders', [
        'items' => [
            ['product_name' => 'Laptop', 'quantity' => 1, 'unit_price' => 100, 'tax_rate' => 10],
        ],
        'discount_rate' => 10,
        'payments' => [['method' => 'card', 'amount' => 99]],
    ])->assertCreated()
        ->assertJsonPath('data.subtotal', 100)
        ->assertJsonPath('data.tax_amount', 10)
        ->assertJsonPath('data.discount_amount', 10)
        ->assertJsonPath('data.total', 100)
        ->assertJsonPath('data.paid_amount', 99)
        ->assertJsonPath('data.balance_due', 1)
        ->assertJsonPath('data.payment_status', 'partial');
});

it('supports multiple payment methods on one order', function () {
    $this->postJson('/api/v1/pos-orders', posOrderPayload([
        'payments' => [
            ['method' => 'cash', 'amount' => 10],
            ['method' => 'card', 'amount' => 5],
            ['method' => 'mobile', 'amount' => 6],
        ],
    ]))->assertCreated()
        ->assertJsonPath('data.paid_amount', 21)
        ->assertJsonPath('data.balance_due', 0)
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonCount(3, 'data.payments');
});

it('enriches line prices from the product catalog', function () {
    $category = ProductCategory::factory()->create(['company_id' => $this->company->id]);
    $product = Product::factory()->create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name' => 'Catalogue Item',
        'sale_price' => 25,
        'tax_rate' => 5,
    ]);

    $this->postJson('/api/v1/pos-orders', [
        'items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.items.0.product_name', 'Catalogue Item')
        ->assertJsonPath('data.items.0.unit_price', 25)
        ->assertJsonPath('data.items.0.tax_rate', 5)
        ->assertJsonPath('data.subtotal', 50)
        ->assertJsonPath('data.tax_amount', 2.5)
        ->assertJsonPath('data.total', 52.5);
});

it('validates the POS order payload', function () {
    $this->postJson('/api/v1/pos-orders', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);

    $this->postJson('/api/v1/pos-orders', posOrderPayload([
        'payments' => [['method' => 'bitcoin', 'amount' => 1]],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['payments.0.method']);
});

it('lists POS orders for the acting user’s company only', function () {
    PosOrder::factory()->create(['company_id' => $this->company->id]);
    PosOrder::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/pos-orders')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links'])
        ->assertJsonCount(1, 'data');
});

it('filters POS orders by status and searches by order number', function () {
    PosOrder::factory()->create(['company_id' => $this->company->id, 'status' => 'completed']);
    PosOrder::factory()->held()->create(['company_id' => $this->company->id]);
    $refunded = PosOrder::factory()->refunded()->create(['company_id' => $this->company->id]);

    expect($this->getJson('/api/v1/pos-orders?status=held')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/pos-orders?search='.$refunded->order_number)->json('data'))->toHaveCount(1);
});

it('updates items and re-derives payment status', function () {
    $order = PosOrder::factory()->create([
        'company_id' => $this->company->id,
        'status' => 'completed',
        'subtotal' => 20,
        'tax_amount' => 1,
        'total' => 21,
        'paid_amount' => 0,
        'balance_due' => 21,
    ]);

    $this->putJson('/api/v1/pos-orders/'.$order->id, [
        'payments' => [['method' => 'card', 'amount' => 21]],
    ])->assertOk()
        ->assertJsonPath('data.paid_amount', 21)
        ->assertJsonPath('data.balance_due', 0)
        ->assertJsonPath('data.payment_status', 'paid')
        ->assertJsonCount(1, 'data.payments');
});

it('deletes and restores a POS order', function () {
    $order = PosOrder::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson('/api/v1/pos-orders/'.$order->id)->assertNoContent();
    expect(PosOrder::find($order->id))->toBeNull();

    $this->postJson('/api/v1/pos-orders/'.$order->id.'/restore')->assertOk();
    expect(PosOrder::find($order->id))->not->toBeNull();
});

it('serves a POS summary with sales, transactions, payment mix and top products', function () {
    $this->travelTo(now()->setDate(2026, 6, 15));

    $category = ProductCategory::factory()->create(['company_id' => $this->company->id]);
    $widget = Product::factory()->create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name' => 'Widget',
    ]);
    $gadget = Product::factory()->create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'name' => 'Gadget',
    ]);

    $orderA = PosOrder::factory()->create([
        'company_id' => $this->company->id,
        'order_date' => '2026-06-15',
        'subtotal' => 30,
        'tax_amount' => 0,
        'total' => 30,
        'paid_amount' => 30,
        'balance_due' => 0,
    ]);
    PosOrderItem::create([
        'pos_order_id' => $orderA->id,
        'product_id' => $widget->id,
        'product_name' => 'Widget',
        'quantity' => 3,
        'unit_price' => 10,
        'subtotal' => 30,
        'total' => 30,
    ]);
    PosPayment::create(['pos_order_id' => $orderA->id, 'method' => 'cash', 'amount' => 30, 'paid_at' => now()]);

    $orderB = PosOrder::factory()->create([
        'company_id' => $this->company->id,
        'order_date' => '2026-06-15',
        'subtotal' => 70,
        'tax_amount' => 0,
        'total' => 70,
        'paid_amount' => 70,
        'balance_due' => 0,
    ]);
    PosOrderItem::create([
        'pos_order_id' => $orderB->id,
        'product_id' => $widget->id,
        'product_name' => 'Widget',
        'quantity' => 2,
        'unit_price' => 10,
        'subtotal' => 20,
        'total' => 20,
    ]);
    PosOrderItem::create([
        'pos_order_id' => $orderB->id,
        'product_id' => $gadget->id,
        'product_name' => 'Gadget',
        'quantity' => 1,
        'unit_price' => 50,
        'subtotal' => 50,
        'total' => 50,
    ]);
    PosPayment::create(['pos_order_id' => $orderB->id, 'method' => 'card', 'amount' => 70, 'paid_at' => now()]);

    // Refunds and other days must not leak into the day's takings.
    PosOrder::factory()->refunded()->create([
        'company_id' => $this->company->id,
        'order_date' => '2026-06-15',
        'subtotal' => 500,
        'total' => 500,
    ]);
    PosOrder::factory()->create([
        'company_id' => $this->company->id,
        'order_date' => '2026-06-14',
        'subtotal' => 400,
        'total' => 400,
    ]);

    $this->getJson('/api/v1/pos-orders/summary')
        ->assertOk()
        ->assertJsonPath('company_id', $this->company->id)
        ->assertJsonPath('date', '2026-06-15')
        ->assertJsonPath('todays_sales', 100)
        ->assertJsonPath('todays_transactions', 2)
        ->assertJsonPath('todays_items_sold', 6)
        ->assertJsonPath('todays_tax', 0)
        ->assertJsonPath('average_sale', 50)
        ->assertJsonPath('by_payment_method.0.method', 'card')
        ->assertJsonPath('by_payment_method.0.total', 70)
        ->assertJsonPath('by_payment_method.1.method', 'cash')
        ->assertJsonPath('by_payment_method.1.total', 30)
        ->assertJsonPath('top_products.0.product_name', 'Widget')
        ->assertJsonPath('top_products.0.quantity', 5)
        ->assertJsonPath('top_products.0.total', 50)
        ->assertJsonPath('top_products.1.product_name', 'Gadget')
        ->assertJsonPath('top_products.1.quantity', 1)
        ->assertJsonPath('top_products.1.total', 50);
});

it('keeps the POS order surface behind the pos-orders permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $plain = User::factory()->create(['company_id' => $this->company->id]);
    $plain->assignRole('user');
    Passport::actingAs($plain);

    expect($plain->can('pos-orders.view-any'))->toBeFalse();

    $this->getJson('/api/v1/pos-orders')->assertForbidden();
    $this->getJson('/api/v1/pos-orders/summary')->assertForbidden();
    $this->postJson('/api/v1/pos-orders', posOrderPayload())->assertForbidden();

    $role = Role::create(['name' => 'cashier', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo([
        'pos-orders.view-any', 'pos-orders.view',
        'pos-orders.create', 'pos-orders.update',
    ]);

    $cashier = User::factory()->create(['company_id' => $this->company->id]);
    $cashier->assignRole($role);
    Passport::actingAs($cashier);

    $this->getJson('/api/v1/pos-orders')->assertOk();

    $register = PosRegister::factory()->create(['company_id' => $this->company->id]);
    $this->postJson('/api/v1/pos-orders', posOrderPayload(['register_id' => $register->id]))->assertCreated();

    $order = PosOrder::factory()->create(['company_id' => $this->company->id]);
    $this->deleteJson('/api/v1/pos-orders/'.$order->id)->assertForbidden();
});
