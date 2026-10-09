<?php

use App\Enums\ProjectStatus;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectMilestone;
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
});

function projectPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'ERP rollout',
        'description' => 'Roll the ERP out to the branches.',
        'status' => ProjectStatus::Active->value,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
        'budget' => 25000,
    ], $overrides);
}

it('lists projects for the acting user’s company only', function () {
    Project::factory()->create(['company_id' => $this->company->id]);
    Project::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/projects')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links'])
        ->assertJsonCount(1, 'data');
});

it('creates a project stamped with the acting user’s company', function () {
    $response = $this->postJson('/api/v1/projects', projectPayload());

    $response->assertCreated()
        ->assertJsonPath('data.name', 'ERP rollout')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.budget', 25000);

    expect(Project::count())->toBe(1)
        ->and(Project::first()->created_by)->toBe($this->user->id);
});

it('defaults a project to planning', function () {
    $this->postJson('/api/v1/projects', ['name' => 'New initiative'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'planning');
});

it('validates the project payload', function () {
    $this->postJson('/api/v1/projects', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);

    $this->postJson('/api/v1/projects', projectPayload(['status' => 'archived']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('updates a project', function () {
    $project = Project::factory()->create([
        'company_id' => $this->company->id,
        'status' => ProjectStatus::Planning,
    ]);

    $this->putJson('/api/v1/projects/'.$project->id, [
        'status' => ProjectStatus::Active->value,
        'budget' => 5000,
    ])->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.budget', 5000);
});

it('deletes and restores a project', function () {
    $project = Project::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson('/api/v1/projects/'.$project->id)->assertNoContent();
    expect(Project::find($project->id))->toBeNull();

    $this->postJson('/api/v1/projects/'.$project->id.'/restore')->assertOk();
    expect(Project::find($project->id))->not->toBeNull();
});

it('filters projects by status and owner and searches by name', function () {
    $owner = User::factory()->create(['company_id' => $this->company->id]);

    Project::factory()->active()->create([
        'company_id' => $this->company->id,
        'owner_id' => $owner->id,
        'name' => 'Harbor rollout',
    ]);
    Project::factory()->completed()->create([
        'company_id' => $this->company->id,
        'name' => 'Stillwater audit',
    ]);

    expect($this->getJson('/api/v1/projects?status=active')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/projects?owner_id='.$owner->id)->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/projects?search=Harbor')->json('data'))->toHaveCount(1);
});

it('serves a project summary endpoint', function () {
    $project = Project::factory()->active()->create([
        'company_id' => $this->company->id,
        'budget' => 10000,
    ]);
    Project::factory()->completed()->create([
        'company_id' => $this->company->id,
        'budget' => 5000,
    ]);

    ProjectTask::factory()->done()->create(['company_id' => $this->company->id, 'project_id' => $project->id]);
    ProjectTask::factory()->overdue()->create(['company_id' => $this->company->id, 'project_id' => $project->id]);
    ProjectMilestone::factory()->overdue()->create(['company_id' => $this->company->id, 'project_id' => $project->id]);

    $this->getJson('/api/v1/projects/summary')
        ->assertOk()
        ->assertJsonPath('company_id', $this->company->id)
        ->assertJsonPath('total_projects', 2)
        ->assertJsonPath('active_projects', 1)
        ->assertJsonPath('completed_projects', 1)
        ->assertJsonPath('total_budget', 15000)
        ->assertJsonPath('total_tasks', 2)
        ->assertJsonPath('completed_tasks', 1)
        ->assertJsonPath('overdue_tasks', 1)
        ->assertJsonPath('overdue_milestones', 1)
        ->assertJsonPath('by_status.1.status', 'active')
        ->assertJsonPath('by_status.1.count', 1)
        ->assertJsonPath('tasks_by_status.3.status', 'done')
        ->assertJsonPath('tasks_by_status.3.count', 1);
});

it('falls back to the acting user’s company on the summary', function () {
    $this->getJson('/api/v1/projects/summary')
        ->assertOk()
        ->assertJsonPath('company_id', $this->company->id)
        ->assertJsonPath('total_projects', 0);
});

it('feeds the dashboard project summary card', function () {
    Project::factory()->active()->create(['company_id' => $this->company->id]);

    $this->getJson('/api/v1/dashboard/project-summary?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonPath('company_id', $this->company->id)
        ->assertJsonPath('total_projects', 1)
        ->assertJsonPath('active_projects', 1)
        ->assertJsonPath('data.recent_projects.0.company_id', $this->company->id);
});

it('keeps the project surface behind the projects permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $plain = User::factory()->create(['company_id' => $this->company->id]);
    $plain->assignRole('user');
    Passport::actingAs($plain);

    expect($plain->can('projects.view-any'))->toBeFalse();

    $this->getJson('/api/v1/projects')->assertForbidden();
    $this->postJson('/api/v1/projects', projectPayload())->assertForbidden();
    $this->getJson('/api/v1/projects/summary')->assertForbidden();

    $role = Role::create(['name' => 'delivery-lead', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo(['projects.view-any', 'projects.view', 'projects.create', 'projects.update']);

    $lead = User::factory()->create(['company_id' => $this->company->id]);
    $lead->assignRole($role);
    Passport::actingAs($lead);

    $this->getJson('/api/v1/projects')->assertOk();

    $project = Project::factory()->create(['company_id' => $this->company->id]);
    $this->putJson('/api/v1/projects/'.$project->id, ['status' => ProjectStatus::Completed->value])->assertOk();
    $this->deleteJson('/api/v1/projects/'.$project->id)->assertForbidden();
});
