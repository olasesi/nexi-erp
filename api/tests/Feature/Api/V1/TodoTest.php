<?php

use App\Models\Company;
use App\Models\Todo;
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

function aTodo(): Todo
{
    return Todo::factory()->create(['company_id' => test()->company->id]);
}

function grantTodoPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "todos.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'todos.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/todos')->assertUnauthorized();
});

it('blocks users without the todo permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "todos.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/todos')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantTodoPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/todos')->assertOk();

    $this->putJson('/api/v1/addons/todo', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/todos')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'todo');
});

it('creates a todo defaulting its status to pending and priority to medium', function () {
    $this->postJson('/api/v1/todos', [
        'title' => 'Prepare monthly report',
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Prepare monthly report')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.priority', 'medium')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('completes a pending todo and refuses to complete it twice', function () {
    $todo = aTodo();

    $this->postJson("/api/v1/todos/{$todo->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->assertNotNull($todo->fresh()->completed_at);

    $this->postJson("/api/v1/todos/{$todo->id}/complete")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only an open to-do can be completed.');
});

it('filters todos by status', function () {
    aTodo();
    $completed = aTodo();
    $this->postJson("/api/v1/todos/{$completed->id}/complete")->assertOk();

    $this->getJson('/api/v1/todos?status=pending')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'pending');
});

it('filters todos by priority', function () {
    aTodo()->update(['priority' => 'high']);
    aTodo();

    $this->getJson('/api/v1/todos?priority=high')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.priority', 'high');
});

it('scopes todos to the acting user’s company', function () {
    aTodo();
    aTodo()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/todos')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
