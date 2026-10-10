<?php

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\Company;
use App\Models\Contact;
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

function assetCategory(): AssetCategory
{
    return AssetCategory::factory()->create(['company_id' => test()->company->id]);
}

function assetRecord(?AssetCategory $category = null): Asset
{
    return Asset::factory()->create([
        'company_id' => test()->company->id,
        'asset_category_id' => ($category ?? assetCategory())->id,
    ]);
}

function grantAssetPermissions(User $user): void
{
    $names = [];

    foreach (['asset-categories', 'assets', 'asset-assignments'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
            $names[] = "{$module}.{$action}";
        }
    }

    $user->givePermissionTo($names);
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/assets')->assertUnauthorized();
});

it('blocks users without the asset permissions', function () {
    foreach (['asset-categories', 'assets', 'asset-assignments'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/assets')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantAssetPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/assets')->assertOk();

    $this->putJson('/api/v1/addons/asset-management', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/assets')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'asset-management');
});

it('creates an asset category', function () {
    $this->postJson('/api/v1/asset-categories', [
        'name' => 'IT Equipment',
        'description' => 'Laptops, monitors and peripherals.',
        'depreciation_rate' => 20,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'IT Equipment')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.depreciation_rate', 20)
        ->assertJsonPath('data.company_id', $this->company->id);

    $this->assertDatabaseHas('asset_categories', ['name' => 'IT Equipment']);
});

it('creates an asset with a default status of available', function () {
    $category = assetCategory();

    $this->postJson('/api/v1/assets', [
        'asset_category_id' => $category->id,
        'asset_tag' => 'AST-1001',
        'name' => 'MacBook Pro',
        'serial_number' => 'SN-ABC-123',
        'purchase_cost' => 1500,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'MacBook Pro')
        ->assertJsonPath('data.asset_tag', 'AST-1001')
        ->assertJsonPath('data.status', 'available')
        ->assertJsonPath('data.condition', 'good')
        ->assertJsonPath('data.asset_category_id', $category->id)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects a duplicate asset tag within the same company', function () {
    $existing = assetRecord();

    $this->postJson('/api/v1/assets', [
        'asset_tag' => $existing->asset_tag,
        'name' => 'Duplicate Tag',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['asset_tag']);
});

it('allows the same asset tag in a different company', function () {
    $tag = assetRecord()->asset_tag;

    $this->postJson('/api/v1/assets', [
        'company_id' => Company::factory()->create()->id,
        'asset_tag' => $tag,
        'name' => 'Same Tag Elsewhere',
    ])->assertCreated();
});

it('assigns an asset to a user and records the assignment', function () {
    $asset = assetRecord();
    $target = User::factory()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/assets/{$asset->id}/assign", ['user_id' => $target->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'assigned')
        ->assertJsonPath('data.assigned_to', $target->id);

    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'assigned', 'assigned_to' => $target->id]);

    $this->assertDatabaseHas('asset_assignments', [
        'asset_id' => $asset->id,
        'user_id' => $target->id,
        'returned_at' => null,
    ]);
});

it('assigns an asset to a contact when no user is given', function () {
    $asset = assetRecord();
    $contact = Contact::factory()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/assets/{$asset->id}/assign", ['contact_id' => $contact->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'assigned')
        ->assertJsonPath('data.assigned_to', null);

    $this->assertDatabaseHas('asset_assignments', [
        'asset_id' => $asset->id,
        'contact_id' => $contact->id,
        'user_id' => null,
        'returned_at' => null,
    ]);
});

it('requires at least one assignee', function () {
    $asset = assetRecord();

    $this->postJson("/api/v1/assets/{$asset->id}/assign", [])->assertUnprocessable();
});

it('blocks assigning an asset that is already assigned', function () {
    $asset = assetRecord();
    $target = User::factory()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/assets/{$asset->id}/assign", ['user_id' => $target->id])->assertOk();

    $this->postJson("/api/v1/assets/{$asset->id}/assign", ['user_id' => $target->id])->assertStatus(422);
});

it('returns an asset and closes its open assignment', function () {
    $asset = assetRecord();
    $target = User::factory()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/assets/{$asset->id}/assign", ['user_id' => $target->id])->assertOk();

    $this->postJson("/api/v1/assets/{$asset->id}/return", ['condition_on_return' => 'fair'])
        ->assertOk()
        ->assertJsonPath('data.status', 'available')
        ->assertJsonPath('data.assigned_to', null);

    $this->assertDatabaseHas('asset_assignments', [
        'asset_id' => $asset->id,
        'condition_on_return' => 'fair',
    ]);
    $this->assertDatabaseMissing('asset_assignments', [
        'asset_id' => $asset->id,
        'returned_at' => null,
    ]);
});

it('refuses to return an asset that is not assigned', function () {
    $asset = assetRecord();

    $this->postJson("/api/v1/assets/{$asset->id}/return")->assertStatus(422);
});

it('retires an asset', function () {
    $asset = assetRecord();
    $asset->update(['status' => Asset::STATUS_MAINTENANCE]);

    $this->postJson("/api/v1/assets/{$asset->id}/retire")
        ->assertOk()
        ->assertJsonPath('data.status', 'retired');

    $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'retired', 'assigned_to' => null]);
});

it('retiring a retired asset returns 422', function () {
    $asset = assetRecord();

    $this->postJson("/api/v1/assets/{$asset->id}/retire")->assertOk();
    $this->postJson("/api/v1/assets/{$asset->id}/retire")->assertStatus(422);
});

it('lists only open assignments when requested', function () {
    $asset = assetRecord();
    $target = User::factory()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/assets/{$asset->id}/assign", ['user_id' => $target->id])->assertOk();
    $this->postJson("/api/v1/assets/{$asset->id}/return")->assertOk();

    $this->getJson('/api/v1/asset-assignments?open=1')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson('/api/v1/asset-assignments')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('scopes assets to the acting user’s company', function () {
    assetRecord();
    Asset::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/assets')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $foreign = Asset::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson("/api/v1/assets/{$foreign->id}")->assertNotFound();
});

it('scopes assignment lookups to the acting user’s company', function () {
    $asset = assetRecord();
    $target = User::factory()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/assets/{$asset->id}/assign", ['user_id' => $target->id])->assertOk();

    AssetAssignment::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/asset-assignments')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
