<?php

use App\Models\Company;
use App\Models\Goal;
use App\Models\GoalKeyResult;
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

function goal(array $overrides = []): Goal
{
    return Goal::factory()->create(array_merge(['company_id' => test()->company->id], $overrides));
}

function grantGoalPermissions(User $user): void
{
    foreach (['goals', 'goal-key-results'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(
        Permission::where(fn ($query) => $query
            ->where('name', 'like', 'goals.%')
            ->orWhere('name', 'like', 'goal-key-results.%'))
            ->get()
    );
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/goals')->assertUnauthorized();
    $this->getJson('/api/v1/goal-key-results')->assertUnauthorized();
});

it('blocks users without the goals permissions', function () {
    foreach (['goals', 'goal-key-results'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/goals')->assertForbidden();
    $this->postJson('/api/v1/goals', ['title' => 'Hack'])->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantGoalPermissions($this->user);
    Permission::firstOrCreate(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/goals')->assertOk();

    $this->putJson('/api/v1/addons/goals', ['enabled' => false])
        ->assertOk()
        ->assertJsonPath('data.enabled', false);

    $this->getJson('/api/v1/goals')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'goals');
});

it('creates a goal with a draft status and zero progress', function () {
    $this->postJson('/api/v1/goals', [
        'title' => 'Grow MRR',
        'target_value' => 5000,
        'unit' => 'USD',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Grow MRR')
        ->assertJsonPath('data.type', 'objective')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.progress', 0)
        ->assertJsonPath('data.current_value', 0)
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(Goal::count())->toBe(1);
});

it('recomputes the percent progress and auto-completes at 100', function () {
    $item = goal(['target_value' => 200]);

    $this->postJson("/api/v1/goals/{$item->id}/progress", ['current_value' => 50])
        ->assertOk()
        ->assertJsonPath('data.current_value', 50)
        ->assertJsonPath('data.progress', 25)
        ->assertJsonPath('data.status', 'draft');

    $this->postJson("/api/v1/goals/{$item->id}/progress", ['current_value' => 500])
        ->assertOk()
        ->assertJsonPath('data.current_value', 500)
        ->assertJsonPath('data.progress', 100)
        ->assertJsonPath('data.status', 'completed');
});

it('refuses to cancel a completed or already-cancelled goal', function () {
    $completed = goal(['status' => Goal::STATUS_ACTIVE]);
    $this->postJson("/api/v1/goals/{$completed->id}/progress", ['current_value' => 100])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->postJson("/api/v1/goals/{$completed->id}/cancel")->assertStatus(422);

    $cancelled = goal(['status' => Goal::STATUS_ACTIVE]);
    $this->postJson("/api/v1/goals/{$cancelled->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $this->postJson("/api/v1/goals/{$cancelled->id}/cancel")->assertStatus(422);
});

it('recomputes key result progress on create and update', function () {
    $item = goal(['target_value' => 100]);

    $created = $this->postJson('/api/v1/goal-key-results', [
        'goal_id' => $item->id,
        'title' => 'Sign 100 customers',
        'target_value' => 200,
        'current_value' => 50,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Sign 100 customers')
        ->assertJsonPath('data.progress', 25)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.company_id', $this->company->id);

    $krId = $created->json('data.id');

    $this->putJson("/api/v1/goal-key-results/{$krId}", ['current_value' => 200])
        ->assertOk()
        ->assertJsonPath('data.current_value', 200)
        ->assertJsonPath('data.progress', 100);

    $this->deleteJson("/api/v1/goal-key-results/{$krId}")->assertNoContent();
    $this->postJson("/api/v1/goal-key-results/{$krId}/restore")
        ->assertOk()
        ->assertJsonPath('data.title', 'Sign 100 customers');
});

it('scopes goals and key results to the acting user’s company', function () {
    $mine = goal();
    GoalKeyResult::factory()->create(['company_id' => $this->company->id, 'goal_id' => $mine->id]);

    Goal::factory()->create(['company_id' => Company::factory()->create()->id, 'title' => 'Other company goal']);
    GoalKeyResult::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/goals')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/goal-key-results')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
