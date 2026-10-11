<?php

use App\Models\Award;
use App\Models\Company;
use App\Models\HrmEmployee;
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

function awardEmployee(): HrmEmployee
{
    return HrmEmployee::factory()->create(['company_id' => test()->company->id]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function awardRecord(array $attributes = []): Award
{
    return Award::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'employee_id' => awardEmployee()->id,
    ], $attributes));
}

function grantAwardPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "awards.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'awards.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/awards')->assertUnauthorized();
});

it('blocks users without the awards permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "awards.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/awards')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantAwardPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/awards')->assertOk();

    $this->putJson('/api/v1/addons/award', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/awards')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'award');
});

it('creates an award', function () {
    $employee = awardEmployee();

    $this->postJson('/api/v1/awards', [
        'employee_id' => $employee->id,
        'award_type' => 'Employee of the Month',
        'title' => 'October Star',
        'description' => 'Outstanding effort.',
        'awarded_date' => now()->toDateString(),
        'gift' => 'Gift Card',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'October Star')
        ->assertJsonPath('data.award_type', 'Employee of the Month')
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.created_by', $this->user->id)
        ->assertJsonPath('data.employee.id', $employee->id);
});

it('rejects an invalid employee id and missing title', function () {
    $this->postJson('/api/v1/awards', [
        'employee_id' => 999999,
        'award_type' => 'Spot Award',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['employee_id', 'title']);
});

it('filters awards by award type', function () {
    awardRecord(['award_type' => 'Spot Award']);
    awardRecord(['award_type' => 'Long Service']);

    $this->getJson('/api/v1/awards?award_type='.urlencode('Spot Award'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.award_type', 'Spot Award');
});

it('scopes awards to the acting user’s company', function () {
    awardRecord();
    awardRecord(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/awards')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
