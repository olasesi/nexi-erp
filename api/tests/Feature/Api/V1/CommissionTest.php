<?php

use App\Models\Commission;
use App\Models\Company;
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

function commission(array $overrides = []): Commission
{
    return Commission::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $overrides));
}

function grantCommissionPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "commissions.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'commissions.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/commissions')->assertUnauthorized();
});

it('blocks users without the commission permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create([
            'name' => "commissions.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/commissions')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantCommissionPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/commissions')->assertOk();

    $this->putJson('/api/v1/addons/commission', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/commissions')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'commission');
});

it('creates a commission computing the amount from sale amount and rate', function () {
    $this->postJson('/api/v1/commissions', [
        'title' => 'Q1 Sales',
        'reference' => 'INV-100',
        'sale_amount' => 10000,
        'rate' => 5,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Q1 Sales')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.amount', 500)
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(Commission::first()?->amount)->toBe(500.0);
});

it('keeps an explicitly supplied amount', function () {
    $this->postJson('/api/v1/commissions', [
        'title' => 'Q1 Sales',
        'sale_amount' => 10000,
        'rate' => 5,
        'amount' => 123.45,
    ])
        ->assertCreated()
        ->assertJsonPath('data.amount', 123.45);
});

it('rejects an invalid status', function () {
    $this->postJson('/api/v1/commissions', [
        'title' => 'Q1 Sales',
        'status' => 'archived',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('approves a pending commission and refuses a second approval', function () {
    $pending = commission();

    $this->postJson("/api/v1/commissions/{$pending->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->postJson("/api/v1/commissions/{$pending->id}/approve")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only a pending commission can be approved.');
});

it('marks an approved commission paid and refuses an unapproved one', function () {
    $approved = commission(['status' => Commission::STATUS_APPROVED]);
    $pending = commission();

    $this->postJson("/api/v1/commissions/{$approved->id}/mark-paid")
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    $this->postJson("/api/v1/commissions/{$pending->id}/mark-paid")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only an approved commission can be marked paid.');
});

it('filters commissions by status', function () {
    commission(['status' => Commission::STATUS_PENDING]);
    commission(['status' => Commission::STATUS_APPROVED]);

    $this->getJson('/api/v1/commissions?status=pending')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'pending');
});

it('scopes commissions to the acting user’s company', function () {
    commission();
    commission(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/commissions')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
