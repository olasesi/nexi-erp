<?php

use App\Models\Company;
use App\Models\LaundryItem;
use App\Models\LaundryOrder;
use App\Models\User;
use App\Support\PermissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);
});

function laundryOrder(): LaundryOrder
{
    return LaundryOrder::factory()->create(['company_id' => test()->company->id]);
}

function grantLaundryPermissions(User $user): void
{
    foreach (['laundry-orders', 'laundry-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'laundry-%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/laundry-orders')->assertUnauthorized();
});

it('blocks users without the laundry permissions', function () {
    foreach (['laundry-orders', 'laundry-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/laundry-orders')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantLaundryPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/laundry-orders')->assertOk();

    $this->putJson('/api/v1/addons/laundry-dry-cleaning', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/laundry-orders')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'laundry-dry-cleaning');
});

it('creates an order with a generated number and default status', function () {
    $this->postJson('/api/v1/laundry-orders', ['notes' => 'Two suits'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'received')
        ->assertJsonPath('data.order_number', 'LW-'.date('ym').'-0001')
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.created_by', $this->user->id)
        ->assertJsonPath('data.next_status', 'washing');

    $this->postJson('/api/v1/laundry-orders', [])
        ->assertCreated()
        ->assertJsonPath('data.order_number', 'LW-'.date('ym').'-0002');
});

it('validates the order payload', function () {
    $this->postJson('/api/v1/laundry-orders', ['tax_rate' => 150])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tax_rate']);
});

it('advances an order through the garment flow and stamps delivery', function () {
    $order = laundryOrder();

    foreach (['washing', 'drying', 'folding', 'ready_for_pickup'] as $expected) {
        $this->postJson("/api/v1/laundry-orders/{$order->id}/advance")
            ->assertOk()
            ->assertJsonPath('data.status', $expected);
    }

    $this->postJson("/api/v1/laundry-orders/{$order->id}/advance")
        ->assertOk()
        ->assertJsonPath('data.status', 'delivered')
        ->assertJsonPath('data.next_status', null);

    expect($order->fresh()->completed_at)->not->toBeNull();

    $this->postJson("/api/v1/laundry-orders/{$order->id}/advance")->assertStatus(422);
});

it('cancels an order still in the flow but not a delivered one', function () {
    $order = laundryOrder();

    $this->postJson("/api/v1/laundry-orders/{$order->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->postJson("/api/v1/laundry-orders/{$order->id}/advance")->assertStatus(422);

    $delivered = LaundryOrder::factory()->delivered()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/laundry-orders/{$delivered->id}/cancel")->assertStatus(422);
});

it('computes order totals from its items', function () {
    $order = LaundryOrder::factory()->create([
        'company_id' => $this->company->id,
        'tax_rate' => 10,
    ]);

    $this->postJson('/api/v1/laundry-items', [
        'laundry_order_id' => $order->id,
        'description' => 'Dress shirt',
        'service' => 'dry_clean',
        'quantity' => 2,
        'unit_price' => 10,
    ])
        ->assertCreated()
        ->assertJsonPath('data.subtotal', 20)
        ->assertJsonPath('data.company_id', $this->company->id);

    $order->refresh();
    expect($order->subtotal)->toBe(20.0)
        ->and($order->tax_amount)->toBe(2.0)
        ->and($order->total)->toBe(22.0);

    $item = LaundryItem::firstOrFail();

    $this->putJson("/api/v1/laundry-items/{$item->id}", ['quantity' => 3])
        ->assertOk()
        ->assertJsonPath('data.subtotal', 30);

    expect($order->fresh()->total)->toBe(33.0);

    $this->deleteJson("/api/v1/laundry-items/{$item->id}")->assertNoContent();

    $order->refresh();
    expect($order->subtotal)->toBe(0.0)
        ->and($order->total)->toBe(0.0);
});

it('applies a discount to the order total', function () {
    $order = LaundryOrder::factory()->create([
        'company_id' => $this->company->id,
        'discount_amount' => 5,
    ]);

    $this->postJson('/api/v1/laundry-items', [
        'laundry_order_id' => $order->id,
        'description' => 'Coat',
        'quantity' => 1,
        'unit_price' => 20,
    ])->assertCreated();

    $order->refresh();
    expect($order->subtotal)->toBe(20.0)
        ->and($order->total)->toBe(15.0);
});

it('recalculates when the order tax rate changes', function () {
    $order = LaundryOrder::factory()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/v1/laundry-items', [
        'laundry_order_id' => $order->id,
        'description' => 'Sheets',
        'quantity' => 4,
        'unit_price' => 25,
    ])->assertCreated();

    $this->putJson("/api/v1/laundry-orders/{$order->id}", ['tax_rate' => 20])->assertOk();

    $order->refresh();
    expect($order->subtotal)->toBe(100.0)
        ->and($order->tax_amount)->toBe(20.0)
        ->and($order->total)->toBe(120.0);
});

it('refuses to touch the items of a delivered order', function () {
    $order = LaundryOrder::factory()->delivered()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/v1/laundry-items', [
        'laundry_order_id' => $order->id,
        'description' => 'Late addition',
        'quantity' => 1,
        'unit_price' => 5,
    ])->assertStatus(422);
});

it('keeps delivered orders but deletes open ones', function () {
    $open = laundryOrder();
    $delivered = LaundryOrder::factory()->delivered()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/laundry-orders/{$open->id}")->assertNoContent();
    $this->deleteJson("/api/v1/laundry-orders/{$delivered->id}")->assertStatus(422);
});

it('summarises the pipeline and its value', function () {
    LaundryOrder::factory()->create(['company_id' => $this->company->id, 'status' => 'received', 'total' => 40]);
    LaundryOrder::factory()->washing()->create(['company_id' => $this->company->id, 'total' => 60]);
    LaundryOrder::factory()->delivered()->create(['company_id' => $this->company->id, 'total' => 100]);
    LaundryOrder::factory()->cancelled()->create(['company_id' => $this->company->id, 'total' => 99]);

    $this->getJson('/api/v1/laundry-orders/summary')
        ->assertOk()
        ->assertJsonPath('summary.total_orders', 4)
        ->assertJsonPath('summary.by_status.received', 1)
        ->assertJsonPath('summary.by_status.washing', 1)
        ->assertJsonPath('summary.by_status.delivered', 1)
        ->assertJsonPath('summary.by_status.cancelled', 1)
        ->assertJsonPath('summary.open_value', 100)
        ->assertJsonPath('summary.delivered_value', 100);
});

it('scopes orders to the acting user’s company', function () {
    LaundryOrder::factory()->create(['company_id' => $this->company->id]);
    LaundryOrder::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/laundry-orders')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
