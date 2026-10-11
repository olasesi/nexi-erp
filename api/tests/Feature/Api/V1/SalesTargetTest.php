<?php

use App\Models\Company;
use App\Models\HrmEmployee;
use App\Models\SalesTarget;
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
 * @param  array<string, mixed>  $overrides
 */
function salesTarget(array $overrides = []): SalesTarget
{
    $employee = HrmEmployee::factory()->create(['company_id' => test()->company->id]);

    return SalesTarget::factory()->create(array_merge([
        'company_id' => test()->company->id,
        'employee_id' => $employee->id,
    ], $overrides));
}

function grantSalesTargetPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "sales-targets.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'sales-targets.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/sales-targets')->assertUnauthorized();
});

it('blocks users without the sales target permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "sales-targets.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/sales-targets')->assertForbidden();
    $this->postJson('/api/v1/sales-targets', ['title' => 'Hack'])->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantSalesTargetPermissions($this->user);
    Permission::firstOrCreate(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/sales-targets')->assertOk();

    $this->putJson('/api/v1/addons/sales-target', ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.enabled', false);

    $this->getJson('/api/v1/sales-targets')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'sales-target');
});

it('creates a sales target defaulting its status to active', function () {
    $employee = HrmEmployee::factory()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/v1/sales-targets', [
        'employee_id' => $employee->id,
        'title' => 'Q4 New Business',
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'target_amount' => 25000,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Q4 New Business')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.period_type', 'monthly')
        ->assertJsonPath('data.target_amount', 25000)
        ->assertJsonPath('data.employee.id', $employee->id)
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(SalesTarget::count())->toBe(1);
});

it('marks a target achieved when progress meets the target and active otherwise', function () {
    $target = salesTarget(['target_amount' => 1000, 'achieved_amount' => 0]);

    $this->postJson("/api/v1/sales-targets/{$target->id}/progress", ['achieved_amount' => 400])
        ->assertOk()
        ->assertJsonPath('data.achieved_amount', 400)
        ->assertJsonPath('data.progress_percentage', 40)
        ->assertJsonPath('data.status', 'active');

    $this->postJson("/api/v1/sales-targets/{$target->id}/progress", ['achieved_amount' => 1000])
        ->assertOk()
        ->assertJsonPath('data.achieved_amount', 1000)
        ->assertJsonPath('data.progress_percentage', 100)
        ->assertJsonPath('data.status', 'achieved');
});

it('reports the progress percentage capped between zero and one hundred', function () {
    $overAchieved = salesTarget(['target_amount' => 1000, 'achieved_amount' => 1500]);

    $this->getJson("/api/v1/sales-targets/{$overAchieved->id}")
        ->assertOk()
        ->assertJsonPath('data.progress_percentage', 100);

    $noTarget = salesTarget(['target_amount' => 0, 'achieved_amount' => 500]);

    $this->getJson("/api/v1/sales-targets/{$noTarget->id}")
        ->assertOk()
        ->assertJsonPath('data.progress_percentage', 0);
});

it('filters sales targets by status', function () {
    salesTarget(['status' => SalesTarget::STATUS_ACTIVE]);
    salesTarget(['status' => SalesTarget::STATUS_ACHIEVED]);

    $this->getJson('/api/v1/sales-targets?status=achieved')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'achieved');
});

it('scopes sales targets to the acting user’s company', function () {
    salesTarget();

    $otherCompany = Company::factory()->create();
    SalesTarget::factory()->create([
        'company_id' => $otherCompany->id,
        'employee_id' => HrmEmployee::factory()->create(['company_id' => $otherCompany->id])->id,
    ]);

    $this->getJson('/api/v1/sales-targets')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
