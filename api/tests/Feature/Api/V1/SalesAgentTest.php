<?php

use App\Models\Company;
use App\Models\SalesAgent;
use App\Models\SalesAgentOrder;
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

/**
 * @param  array<string, mixed>  $attributes
 */
function salesAgent(array $attributes = []): SalesAgent
{
    return SalesAgent::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $attributes));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function salesAgentOrder(SalesAgent $agent, array $attributes = []): SalesAgentOrder
{
    return SalesAgentOrder::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'sales_agent_id' => $agent->id,
    ], $attributes));
}

function grantSalesAgentPermissions(User $user): void
{
    foreach (['sales-agents', 'sales-agent-orders'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'sales-agent%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/sales-agents')->assertUnauthorized();
});

it('blocks users without the sales agent permissions', function () {
    foreach (['sales-agents', 'sales-agent-orders'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/sales-agents')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantSalesAgentPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/sales-agents')->assertOk();

    $this->putJson('/api/v1/addons/sales-agent', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/sales-agents')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'sales-agent');
});

it('creates a sales agent defaulting its status to pending', function () {
    grantSalesAgentPermissions($this->user);

    $this->postJson('/api/v1/sales-agents', [
        'name' => 'Referral Partner',
        'email' => 'agent@example.com',
        'phone' => '555-0100',
        'commission_rate' => 7.5,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Referral Partner')
        ->assertJsonPath('data.email', 'agent@example.com')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.commission_rate', 7.5)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('requests a pending agent and refuses anything else', function () {
    grantSalesAgentPermissions($this->user);
    $agent = salesAgent();

    $this->postJson("/api/v1/sales-agents/{$agent->id}/request")
        ->assertOk()
        ->assertJsonPath('data.status', 'requested');

    expect($agent->fresh()->requested_at)->not->toBeNull();

    $this->postJson("/api/v1/sales-agents/{$agent->id}/request")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only a pending agent can be requested.');
});

it('accepts a requested agent and refuses a pending one', function () {
    grantSalesAgentPermissions($this->user);
    $requested = salesAgent(['status' => SalesAgent::STATUS_REQUESTED]);
    $pending = salesAgent();

    $this->postJson("/api/v1/sales-agents/{$requested->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect($requested->fresh()->approved_at)->not->toBeNull();

    $this->postJson("/api/v1/sales-agents/{$pending->id}/accept")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only a requested agent can be accepted.');
});

it('rejects a requested agent and refuses a pending one', function () {
    grantSalesAgentPermissions($this->user);
    $requested = salesAgent(['status' => SalesAgent::STATUS_REQUESTED]);
    $pending = salesAgent();

    $this->postJson("/api/v1/sales-agents/{$requested->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->postJson("/api/v1/sales-agents/{$pending->id}/reject")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only a requested agent can be rejected.');
});

it('creates an order deriving the commission amount from the agent rate', function () {
    grantSalesAgentPermissions($this->user);
    $agent = salesAgent(['commission_rate' => 5]);

    $this->postJson('/api/v1/sales-agent-orders', [
        'sales_agent_id' => $agent->id,
        'order_number' => 'ORD-1001',
        'customer_name' => 'Acme Corp',
        'amount' => 2000,
    ])
        ->assertCreated()
        ->assertJsonPath('data.order_number', 'ORD-1001')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.commission_amount', 100)
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(SalesAgentOrder::first()?->commission_amount)->toBe(100.0);
});

it('keeps an explicitly supplied commission amount', function () {
    grantSalesAgentPermissions($this->user);
    $agent = salesAgent(['commission_rate' => 5]);

    $this->postJson('/api/v1/sales-agent-orders', [
        'sales_agent_id' => $agent->id,
        'order_number' => 'ORD-1002',
        'amount' => 2000,
        'commission_amount' => 123.45,
    ])
        ->assertCreated()
        ->assertJsonPath('data.commission_amount', 123.45);
});

it('updates an order status and rejects an invalid one', function () {
    grantSalesAgentPermissions($this->user);
    $order = salesAgentOrder(salesAgent());

    $this->postJson("/api/v1/sales-agent-orders/{$order->id}/update-status", [
        'status' => SalesAgentOrder::STATUS_CONFIRMED,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'confirmed');

    $this->postJson("/api/v1/sales-agent-orders/{$order->id}/update-status", [
        'status' => 'bogus',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('filters agents by status', function () {
    grantSalesAgentPermissions($this->user);
    salesAgent(['status' => SalesAgent::STATUS_PENDING]);
    salesAgent(['status' => SalesAgent::STATUS_ACTIVE]);

    $this->getJson('/api/v1/sales-agents?status=pending')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'pending');
});

it('scopes agents to the acting user’s company', function () {
    grantSalesAgentPermissions($this->user);
    salesAgent();
    salesAgent(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/sales-agents')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
