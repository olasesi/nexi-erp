<?php

use App\Models\Company;
use App\Models\ProductUnit;
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

function productUnit(): ProductUnit
{
    return ProductUnit::factory()->create(['company_id' => test()->company->id]);
}

function grantProductUnitPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "product-units.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'product-units.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/product-units')->assertUnauthorized();
});

it('blocks users without the product unit permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "product-units.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/product-units')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantProductUnitPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/product-units')->assertOk();

    $this->putJson('/api/v1/addons/product-unit', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/product-units')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'product-unit');
});

it('creates a product unit defaulting its operator and active flag', function () {
    grantProductUnitPermissions($this->user);

    $this->postJson('/api/v1/product-units', [
        'name' => 'Kilogram',
        'short_name' => 'kg',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Kilogram')
        ->assertJsonPath('data.short_name', 'kg')
        ->assertJsonPath('data.operator', 'multiply')
        ->assertJsonPath('data.operation_value', 1)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('updates a product unit', function () {
    grantProductUnitPermissions($this->user);
    $unit = productUnit();

    $this->putJson("/api/v1/product-units/{$unit->id}", [
        'name' => 'Gram',
        'operator' => 'divide',
        'operation_value' => 1000,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Gram')
        ->assertJsonPath('data.operator', 'divide')
        ->assertJsonPath('data.operation_value', 1000);
});

it('deletes and restores a product unit', function () {
    grantProductUnitPermissions($this->user);
    $unit = productUnit();

    $this->deleteJson("/api/v1/product-units/{$unit->id}")->assertNoContent();
    $this->assertSoftDeleted($unit);

    $this->postJson("/api/v1/product-units/{$unit->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.id', $unit->id);

    $this->assertDatabaseHas('product_units', ['id' => $unit->id, 'deleted_at' => null]);
});

it('filters product units by is_active', function () {
    grantProductUnitPermissions($this->user);
    productUnit();
    productUnit()->update(['is_active' => false]);

    $this->getJson('/api/v1/product-units?is_active=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_active', true);
});

it('scopes product units to the acting user’s company', function () {
    grantProductUnitPermissions($this->user);
    productUnit();
    productUnit()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/product-units')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
