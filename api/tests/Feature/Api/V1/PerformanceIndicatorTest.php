<?php

use App\Models\Company;
use App\Models\PerformanceIndicator;
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

function performanceIndicator(array $overrides = []): PerformanceIndicator
{
    return PerformanceIndicator::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $overrides));
}

function grantPerformanceIndicatorPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "performance-indicators.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(
        Permission::where('name', 'like', 'performance-indicators.%')->get()
    );
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/performance-indicators')->assertUnauthorized();
});

it('blocks users without the performance-indicators permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create([
            'name' => "performance-indicators.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/performance-indicators')->assertForbidden();

    $this->postJson('/api/v1/performance-indicators', ['name' => 'Defect rate'])
        ->assertForbidden();
});

it('hides the module when the add-on is switched off', function () {
    grantPerformanceIndicatorPermissions($this->user);
    Permission::firstOrCreate(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/performance-indicators')->assertOk();

    $this->putJson('/api/v1/addons/performance-indicator', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/performance-indicators')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'performance-indicator');
});

it('creates an indicator', function () {
    $this->postJson('/api/v1/performance-indicators', [
        'name' => 'On-time delivery',
        'category' => 'Delivery',
        'weight' => 2.5,
        'target_value' => 95,
        'unit' => '%',
        'description' => 'Share of orders shipped by the promised date.',
        'is_active' => true,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'On-time delivery')
        ->assertJsonPath('data.category', 'Delivery')
        ->assertJsonPath('data.weight', 2.5)
        ->assertJsonPath('data.target_value', 95)
        ->assertJsonPath('data.unit', '%')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects a negative weight', function () {
    $this->postJson('/api/v1/performance-indicators', [
        'name' => 'Defect rate',
        'weight' => -1,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['weight']);
});

it('updates an indicator', function () {
    $indicator = performanceIndicator(['name' => 'Old name']);

    $this->putJson("/api/v1/performance-indicators/{$indicator->id}", [
        'name' => 'Response time',
        'category' => 'Quality',
        'weight' => 4.25,
        'target_value' => 99.5,
        'unit' => 'ms',
        'description' => 'Average first response time.',
        'is_active' => false,
    ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Response time')
        ->assertJsonPath('data.category', 'Quality')
        ->assertJsonPath('data.unit', 'ms')
        ->assertJsonPath('data.is_active', false);

    expect($indicator->fresh()->name)->toBe('Response time')
        ->and($indicator->fresh()->weight)->toEqual(4.25)
        ->and($indicator->fresh()->is_active)->toBeFalse();
});

it('deletes and restores an indicator', function () {
    $indicator = performanceIndicator();

    $this->deleteJson("/api/v1/performance-indicators/{$indicator->id}")->assertNoContent();
    expect(PerformanceIndicator::find($indicator->id))->toBeNull();

    $this->postJson("/api/v1/performance-indicators/{$indicator->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.id', $indicator->id);

    expect(PerformanceIndicator::find($indicator->id))->not->toBeNull();
});

it('filters indicators by category', function () {
    performanceIndicator(['category' => 'Quality']);
    performanceIndicator(['category' => 'Delivery']);

    $this->getJson('/api/v1/performance-indicators?category=Delivery')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.category', 'Delivery');
});

it('searches indicators by name', function () {
    performanceIndicator(['name' => 'Defect rate']);
    performanceIndicator(['name' => 'Punctuality']);

    $this->getJson('/api/v1/performance-indicators?search=Defect')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Defect rate');
});

it('scopes indicators to the acting user’s company', function () {
    performanceIndicator(['name' => 'Mine']);
    performanceIndicator([
        'company_id' => Company::factory()->create()->id,
        'name' => 'Theirs',
    ]);

    $this->getJson('/api/v1/performance-indicators')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Mine');
});
