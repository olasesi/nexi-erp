<?php

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectMilestone;
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

function projectMilestonePayload(array $overrides = []): array
{
    return array_merge([
        'project_id' => null,
        'name' => 'Phase 1 sign-off',
        'description' => 'Finance accepts the migrated balances.',
        'due_date' => now()->addMonth()->toDateString(),
        'status' => ProjectMilestone::STATUS_PENDING,
    ], $overrides);
}

it('lists milestones with their project', function () {
    ProjectMilestone::factory()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
    ]);
    ProjectMilestone::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/project-milestones')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.project.id', $this->project->id);
});

it('creates a milestone with a pending default', function () {
    $this->postJson('/api/v1/project-milestones', [
        'project_id' => $this->project->id,
        'name' => 'Go-live',
    ])->assertCreated()
        ->assertJsonPath('data.status', ProjectMilestone::STATUS_PENDING)
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(ProjectMilestone::first()->created_by)->toBe($this->user->id);
});

it('validates the milestone payload', function () {
    $this->postJson('/api/v1/project-milestones', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['project_id', 'name']);

    $this->postJson('/api/v1/project-milestones', projectMilestonePayload([
        'project_id' => $this->project->id,
        'status' => 'someday',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('stamps reached_at when a milestone is met and clears it when reopened', function () {
    $milestone = ProjectMilestone::factory()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
    ]);

    expect($milestone->reached_at)->toBeNull();

    $this->putJson('/api/v1/project-milestones/'.$milestone->id, ['status' => ProjectMilestone::STATUS_REACHED])
        ->assertOk();
    expect($milestone->fresh()->reached_at)->not->toBeNull();

    $this->putJson('/api/v1/project-milestones/'.$milestone->id, ['status' => ProjectMilestone::STATUS_PENDING])
        ->assertOk();
    expect($milestone->fresh()->reached_at)->toBeNull();
});

it('marks overdue pending milestones in the payload', function () {
    ProjectMilestone::factory()->overdue()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
    ]);
    ProjectMilestone::factory()->reached()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
    ]);

    $data = $this->getJson('/api/v1/project-milestones')->assertOk()->json('data');

    $overdue = collect($data)->firstWhere('status', ProjectMilestone::STATUS_PENDING);

    expect($overdue['is_overdue'])->toBeTrue()
        ->and(collect($data)->firstWhere('status', ProjectMilestone::STATUS_REACHED)['is_overdue'])->toBeFalse();
});

it('filters milestones by project and status', function () {
    $otherProject = Project::factory()->create(['company_id' => $this->company->id]);

    ProjectMilestone::factory()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
        'status' => ProjectMilestone::STATUS_PENDING,
    ]);
    ProjectMilestone::factory()->reached()->create([
        'company_id' => $this->company->id,
        'project_id' => $otherProject->id,
    ]);

    expect($this->getJson('/api/v1/project-milestones?project_id='.$this->project->id)->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/project-milestones?status=reached')->json('data'))->toHaveCount(1);
});

it('keeps the milestone surface behind the project-milestones permissions', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $plain = User::factory()->create(['company_id' => $this->company->id]);
    $plain->assignRole('user');
    Passport::actingAs($plain);

    $this->getJson('/api/v1/project-milestones')->assertForbidden();
    $this->postJson('/api/v1/project-milestones', projectMilestonePayload(['project_id' => $this->project->id]))
        ->assertForbidden();

    $role = Role::create(['name' => 'milestone-keeper', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo([
        'project-milestones.view-any',
        'project-milestones.view',
        'project-milestones.create',
        'project-milestones.update',
    ]);

    $keeper = User::factory()->create(['company_id' => $this->company->id]);
    $keeper->assignRole($role);
    Passport::actingAs($keeper);

    $this->getJson('/api/v1/project-milestones')->assertOk();

    $milestone = ProjectMilestone::factory()->create([
        'company_id' => $this->company->id,
        'project_id' => $this->project->id,
    ]);
    $this->putJson('/api/v1/project-milestones/'.$milestone->id, ['status' => ProjectMilestone::STATUS_REACHED])
        ->assertOk();
    $this->deleteJson('/api/v1/project-milestones/'.$milestone->id)->assertForbidden();
});
