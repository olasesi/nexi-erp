<?php

use App\Models\Company;
use App\Models\CustomerGroup;
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

function customerGroup(array $overrides = []): CustomerGroup
{
    return CustomerGroup::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $overrides));
}

function grantCustomerGroupPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "customer-groups.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'customer-groups.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/customer-groups')->assertUnauthorized();
});

it('blocks users without the customer-group permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "customer-groups.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/customer-groups')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantCustomerGroupPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/customer-groups')->assertOk();

    $this->putJson('/api/v1/addons/customer-group', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/customer-groups')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'customer-group');
});

it('creates a customer group defaulting to a zero discount and active', function () {
    $this->postJson('/api/v1/customer-groups', [
        'name' => 'Wholesale',
        'description' => 'Bulk buyers with volume pricing.',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Wholesale')
        ->assertJsonPath('data.description', 'Bulk buyers with volume pricing.')
        ->assertJsonPath('data.discount_rate', 0)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('creates a customer group with an explicit discount rate', function () {
    $this->postJson('/api/v1/customer-groups', [
        'name' => 'VIP',
        'discount_rate' => 12.5,
        'is_active' => false,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'VIP')
        ->assertJsonPath('data.discount_rate', 12.5)
        ->assertJsonPath('data.is_active', false);
});

it('validates the discount rate does not exceed 100', function () {
    $this->postJson('/api/v1/customer-groups', [
        'name' => 'Over Discounted',
        'discount_rate' => 101,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['discount_rate']);
});

it('updates a customer group', function () {
    $group = customerGroup(['name' => 'Old Name']);

    $this->putJson("/api/v1/customer-groups/{$group->id}", [
        'name' => 'New Name',
        'discount_rate' => 7.5,
        'is_active' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.discount_rate', 7.5)
        ->assertJsonPath('data.is_active', false);

    expect($group->fresh()->name)->toBe('New Name');
});

it('soft deletes and restores a customer group', function () {
    $group = customerGroup();

    $this->deleteJson("/api/v1/customer-groups/{$group->id}")->assertNoContent();

    expect(CustomerGroup::find($group->id))->toBeNull()
        ->and(CustomerGroup::withTrashed()->find($group->id))->not->toBeNull();

    $this->postJson("/api/v1/customer-groups/{$group->id}/restore")->assertOk();

    expect(CustomerGroup::find($group->id))->not->toBeNull();
});

it('filters customer groups by is_active', function () {
    customerGroup(['name' => 'Wholesale']);
    customerGroup(['name' => 'Dormant', 'is_active' => false]);

    $this->getJson('/api/v1/customer-groups?is_active=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Wholesale');

    $this->getJson('/api/v1/customer-groups?is_active=0')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Dormant');
});

it('scopes customer groups to the acting user’s company', function () {
    customerGroup();
    customerGroup()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/customer-groups')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
