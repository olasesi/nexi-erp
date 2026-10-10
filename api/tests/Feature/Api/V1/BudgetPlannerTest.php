<?php

use App\Models\Budget;
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

function budget(): Budget
{
    return Budget::factory()->create(['company_id' => test()->company->id]);
}

function grantBudgetPermissions(User $user): void
{
    foreach (['budgets', 'budget-lines'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'budget%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/budgets')->assertUnauthorized();
});

it('blocks users without the budget permissions', function () {
    foreach (['budgets', 'budget-lines'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/budgets')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantBudgetPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/budgets')->assertOk();

    $this->putJson('/api/v1/addons/budget-planner', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/budgets')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'budget-planner');
});

it('creates a budget with draft status by default', function () {
    grantBudgetPermissions($this->user);

    $this->postJson('/api/v1/budgets', [
        'name' => 'Q4 Plan',
        'period_type' => 'quarterly',
        'start_date' => '2026-10-01',
        'end_date' => '2026-12-31',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Q4 Plan')
        ->assertJsonPath('data.period_type', 'quarterly')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.total_planned', 0)
        ->assertJsonPath('data.total_actual', 0)
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.created_by', $this->user->id);
});

it('recomputes the budget totals from its lines', function () {
    grantBudgetPermissions($this->user);
    $budget = budget();

    $this->postJson('/api/v1/budget-lines', [
        'budget_id' => $budget->id,
        'category' => 'Rent',
        'type' => 'expense',
        'planned_amount' => 1000,
        'actual_amount' => 900,
    ])->assertCreated();

    $this->postJson('/api/v1/budget-lines', [
        'budget_id' => $budget->id,
        'category' => 'Sales',
        'type' => 'income',
        'planned_amount' => 2500,
        'actual_amount' => 2600,
    ])->assertCreated();

    $budget->refresh();

    expect($budget->total_planned)->toBe(3500.0)
        ->and($budget->total_actual)->toBe(3500.0);
});

it('approves a draft budget and activates it', function () {
    grantBudgetPermissions($this->user);
    $budget = budget();

    $this->postJson("/api/v1/budgets/{$budget->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
});

it('refuses to approve a budget that is not a draft', function () {
    grantBudgetPermissions($this->user);

    $active = Budget::factory()->active()->create(['company_id' => $this->company->id]);
    $closed = Budget::factory()->closed()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/budgets/{$active->id}/approve")->assertStatus(422);
    $this->postJson("/api/v1/budgets/{$closed->id}/approve")->assertStatus(422);
});

it('closes an active budget', function () {
    grantBudgetPermissions($this->user);
    $budget = Budget::factory()->active()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/budgets/{$budget->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');
});

it('creates updates and deletes a budget line', function () {
    grantBudgetPermissions($this->user);
    $budget = budget();

    $lineId = $this->postJson('/api/v1/budget-lines', [
        'budget_id' => $budget->id,
        'category' => 'Marketing',
        'planned_amount' => 500,
    ])
        ->assertCreated()
        ->assertJsonPath('data.category', 'Marketing')
        ->assertJsonPath('data.type', 'expense')
        ->assertJsonPath('data.planned_amount', 500)
        ->assertJsonPath('data.company_id', $this->company->id)
        ->json('data.id');

    $this->putJson("/api/v1/budget-lines/{$lineId}", [
        'planned_amount' => 800,
        'actual_amount' => 750,
    ])
        ->assertOk()
        ->assertJsonPath('data.planned_amount', 800)
        ->assertJsonPath('data.actual_amount', 750);

    $budget->refresh();
    expect($budget->total_planned)->toBe(800.0)
        ->and($budget->total_actual)->toBe(750.0);

    $this->deleteJson("/api/v1/budget-lines/{$lineId}")->assertNoContent();

    $budget->refresh();
    expect($budget->total_planned)->toBe(0.0)
        ->and($budget->total_actual)->toBe(0.0);
});

it('scopes budgets to the acting user’s company', function () {
    grantBudgetPermissions($this->user);

    Budget::factory()->create(['company_id' => $this->company->id]);
    Budget::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/budgets')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
