<?php

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Support\PermissionGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->user = User::factory()->create(['company_id' => $this->company->id]);
    Passport::actingAs($this->user);

    $this->project = Project::factory()->create(['company_id' => $this->company->id]);
});

function projectTaskPayload(array $overrides = []): array
{
    return array_merge([
        'project_id' => null,
        'title' => 'Draft the migration plan',
        'description' => 'Map legacy tables onto the new schema.',
        'status' => ProjectTask::STATUS_IN_PROGRESS,
        'priority' => ProjectTask::PRIORITY_HIGH,
        'due_date' => now()->addWeek()->toDateString(),
    ], $overrides);
}

it('lists tasks with their project and assignee', function () {
    $assignee = User::factory()->create(['company_id' => $this->company->id]);

    ProjectTask::factory()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
        'assigned_to' => $assignee->id,
    ]);
    ProjectTask::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/project-tasks')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.project.id', $this->project->id)
        ->assertJsonPath('data.0.assignedTo.id', $assignee->id);
});

it('creates a task with default status and priority', function () {
    $this->postJson('/api/v1/project-tasks', [
        'project_id' => $this->project->id,
        'title' => 'Kick-off meeting',
    ])->assertCreated()
        ->assertJsonPath('data.status', ProjectTask::STATUS_TODO)
        ->assertJsonPath('data.priority', ProjectTask::PRIORITY_MEDIUM)
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(ProjectTask::first()->created_by)->toBe($this->user->id);
});

it('validates the task payload', function () {
    $this->postJson('/api/v1/project-tasks', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['project_id', 'title']);

    $this->postJson('/api/v1/project-tasks', projectTaskPayload([
        'project_id' => $this->project->id,
        'priority' => 'whenever',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);
});

it('assigns a task to a teammate', function () {
    $assignee = User::factory()->create(['company_id' => $this->company->id]);
    $task = ProjectTask::factory()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
    ]);

    $this->putJson('/api/v1/project-tasks/'.$task->id, ['assigned_to' => $assignee->id])
        ->assertOk()
        ->assertJsonPath('data.assigned_to', $assignee->id);

    expect($task->fresh()->assigned_to)->toBe($assignee->id);
});

it('stamps completed_at when a task is closed and clears it when reopened', function () {
    $task = ProjectTask::factory()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
    ]);

    expect($task->completed_at)->toBeNull();

    $this->putJson('/api/v1/project-tasks/'.$task->id, ['status' => ProjectTask::STATUS_DONE])->assertOk();
    expect($task->fresh()->completed_at)->not->toBeNull();

    $this->putJson('/api/v1/project-tasks/'.$task->id, ['status' => ProjectTask::STATUS_TODO])->assertOk();
    expect($task->fresh()->completed_at)->toBeNull();
});

it('marks overdue open tasks in the payload', function () {
    ProjectTask::factory()->overdue()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
    ]);
    ProjectTask::factory()->done()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
    ]);

    $data = $this->getJson('/api/v1/project-tasks')->assertOk()->json('data');

    $overdue = collect($data)->firstWhere('status', ProjectTask::STATUS_TODO);

    expect($overdue['is_overdue'])->toBeTrue()
        ->and(collect($data)->firstWhere('status', ProjectTask::STATUS_DONE)['is_overdue'])->toBeFalse();
});

it('filters tasks by project, status and assignee', function () {
    $assignee = User::factory()->create(['company_id' => $this->company->id]);
    $otherProject = Project::factory()->create(['company_id' => $this->company->id]);

    ProjectTask::factory()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
        'assigned_to' => $assignee->id,
        'status' => ProjectTask::STATUS_TODO,
    ]);
    ProjectTask::factory()->done()->create([
        'company_id' => $this->company->id,
        'project_id' => $otherProject->id,
    ]);

    expect($this->getJson('/api/v1/project-tasks?project_id='.$this->project->id)->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/project-tasks?status=done')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/project-tasks?assigned_to='.$assignee->id)->json('data'))->toHaveCount(1);
});

it('keeps the task surface behind the project-tasks permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $plain = User::factory()->create(['company_id' => $this->company->id]);
    $plain->assignRole('user');
    Passport::actingAs($plain);

    $this->getJson('/api/v1/project-tasks')->assertForbidden();
    $this->postJson('/api/v1/project-tasks', projectTaskPayload(['project_id' => $this->project->id]))->assertForbidden();

    $role = Role::create(['name' => 'task-runner', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo(['project-tasks.view-any', 'project-tasks.view', 'project-tasks.create', 'project-tasks.update']);

    $runner = User::factory()->create(['company_id' => $this->company->id]);
    $runner->assignRole($role);
    Passport::actingAs($runner);

    $this->getJson('/api/v1/project-tasks')->assertOk();

    $task = ProjectTask::factory()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
    ]);
    $this->putJson('/api/v1/project-tasks/'.$task->id, ['status' => ProjectTask::STATUS_DONE])->assertOk();
    $this->deleteJson('/api/v1/project-tasks/'.$task->id)->assertForbidden();
});
