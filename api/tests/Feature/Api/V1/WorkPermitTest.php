<?php

use App\Models\Company;
use App\Models\HrmEmployee;
use App\Models\User;
use App\Models\WorkPermit;
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

function workPermit(array $attributes = []): WorkPermit
{
    return WorkPermit::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'employee_id' => HrmEmployee::factory()->create(['company_id' => test()->company->id])->id,
    ], $attributes));
}

function grantWorkPermitPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create([
            'name' => "work-permits.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'work-permits.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/work-permits')->assertUnauthorized();
});

it('blocks users without the work permit permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "work-permits.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/work-permits')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantWorkPermitPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/work-permits')->assertOk();

    $this->putJson('/api/v1/addons/work-permit', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/work-permits')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'work-permit');
});

it('creates a work permit defaulting its status to active', function () {
    $this->postJson('/api/v1/work-permits', [
        'permit_number' => 'WP-1001',
        'permit_type' => 'Work',
        'issuing_country' => 'United Arab Emirates',
        'issued_date' => now()->subYear()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.permit_number', 'WP-1001')
        ->assertJsonPath('data.permit_type', 'Work')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('rejects an invalid status', function () {
    $this->postJson('/api/v1/work-permits', [
        'permit_number' => 'WP-1002',
        'status' => 'paused',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('renews a permit updating its expiry and issued date and setting it active', function () {
    $permit = workPermit([
        'issued_date' => now()->subYear()->toDateString(),
        'expiry_date' => now()->subMonth()->toDateString(),
        'status' => WorkPermit::STATUS_EXPIRED,
    ]);

    $newExpiry = now()->addMonths(6)->toDateString();

    $this->postJson("/api/v1/work-permits/{$permit->id}/renew", [
        'expiry_date' => $newExpiry,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.issued_date', today()->toDateString())
        ->assertJsonPath('data.expiry_date', $newExpiry);
});

it('refuses to renew a revoked permit', function () {
    $permit = workPermit(['status' => WorkPermit::STATUS_REVOKED]);

    $this->postJson("/api/v1/work-permits/{$permit->id}/renew", [
        'expiry_date' => now()->addMonths(6)->toDateString(),
    ])
        ->assertStatus(422)
        ->assertJsonPath('message', 'A revoked permit cannot be renewed.');
});

it('filters permits by status', function () {
    workPermit(['status' => WorkPermit::STATUS_ACTIVE]);
    workPermit(['status' => WorkPermit::STATUS_EXPIRED]);

    $this->getJson('/api/v1/work-permits?status=expired')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'expired');
});

it('scopes permits to the acting user’s company', function () {
    workPermit();
    WorkPermit::factory()->create([
        'company_id' => Company::factory()->create()->id,
        'employee_id' => HrmEmployee::factory()->create(['company_id' => Company::factory()->create()->id])->id,
    ]);

    $this->getJson('/api/v1/work-permits')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
