<?php

use App\Models\Company;
use App\Models\SalesReturn;
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

function salesReturn(): SalesReturn
{
    return SalesReturn::factory()->create([
        'company_id' => test()->company->id,
        'status' => SalesReturn::STATUS_DRAFT,
    ]);
}

function grantSalesReturnPermissions(User $user): void
{
    foreach (['sales-returns', 'sales-return-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'sales-return%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/sales-returns')->assertUnauthorized();
});

it('blocks users without the sales-return permissions', function () {
    foreach (['sales-returns', 'sales-return-items'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/sales-returns')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantSalesReturnPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/sales-returns')->assertOk();

    $this->putJson('/api/v1/addons/sales-return', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/sales-returns')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'sales-return');
});

it('creates a sales return defaulting its status to draft', function () {
    $this->postJson('/api/v1/sales-returns', [
        'return_number' => 'SRT-2026-0001',
        'reason' => 'Damaged on arrival',
    ])
        ->assertCreated()
        ->assertJsonPath('data.return_number', 'SRT-2026-0001')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('completes a draft and refuses to complete it twice', function () {
    $return = salesReturn();

    $this->postJson("/api/v1/sales-returns/{$return->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->postJson("/api/v1/sales-returns/{$return->id}/complete")->assertStatus(422);
});

it('recalculates the return total when an item is added', function () {
    $return = salesReturn();

    $this->postJson('/api/v1/sales-return-items', [
        'sales_return_id' => $return->id,
        'description' => 'Damaged laptop',
        'quantity' => 2,
        'unit_price' => 100,
        'total' => 500,
    ])
        ->assertCreated()
        ->assertJsonPath('data.total', 500)
        ->assertJsonPath('data.company_id', $this->company->id);

    $return->refresh();
    expect($return->subtotal)->toBe(500.0)
        ->and($return->total)->toBe(500.0);
});

it('filters sales returns by status', function () {
    salesReturn();
    SalesReturn::factory()->create([
        'company_id' => $this->company->id,
        'status' => SalesReturn::STATUS_COMPLETED,
    ]);

    $this->getJson('/api/v1/sales-returns?status=draft')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'draft');
});

it('scopes sales returns to the acting user’s company', function () {
    salesReturn();
    SalesReturn::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/sales-returns')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
