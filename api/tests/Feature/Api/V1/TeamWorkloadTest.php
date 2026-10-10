<?php

use App\Models\Company;
use App\Models\HrmEmployee;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Ticket;
use App\Models\Timesheet;
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

function grantTeamWorkloadPermissions(User $user): void
{
    foreach (['view-any', 'view'] as $action) {
        Permission::firstOrCreate([
            'name' => "team-workload.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'team-workload.%')->get());
}

function teamWorkloadEmployee(int $companyId, ?int $userId = null): HrmEmployee
{
    return HrmEmployee::factory()->create([
        'company_id' => $companyId,
        'user_id' => $userId,
    ]);
}

function teamWorkloadTask(int $companyId, int $userId, string $status): ProjectTask
{
    $project = Project::factory()->create(['company_id' => $companyId]);

    return ProjectTask::factory()->create([
        'company_id' => $companyId,
        'project_id' => $project->id,
        'assigned_to' => $userId,
        'status' => $status,
    ]);
}

function teamWorkloadTicket(int $companyId, int $userId, string $status): Ticket
{
    return Ticket::factory()->create([
        'company_id' => $companyId,
        'assigned_to' => $userId,
        'status' => $status,
    ]);
}

function teamWorkloadTimesheet(int $companyId, int $employeeId, string $status): Timesheet
{
    return Timesheet::factory()->create([
        'company_id' => $companyId,
        'employee_id' => $employeeId,
        'status' => $status,
    ]);
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/team-workload')->assertUnauthorized();
});

it('blocks users without the team-workload permissions', function () {
    foreach (['view-any', 'view'] as $action) {
        Permission::firstOrCreate([
            'name' => "team-workload.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/team-workload')->assertForbidden();
    $this->getJson('/api/v1/team-workload/summary')->assertForbidden();
});

it('hides the whole module when the team-workload add-on is switched off', function () {
    grantTeamWorkloadPermissions($this->user);
    Permission::firstOrCreate(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/team-workload')->assertOk();

    $this->putJson('/api/v1/addons/team-workload', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/team-workload')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'team-workload');
});

it('returns an empty workload when the company has no employees', function () {
    grantTeamWorkloadPermissions($this->user);

    $this->getJson('/api/v1/team-workload')
        ->assertOk()
        ->assertJsonCount(0);
});

it('returns per-employee workload rows scoped to the company', function () {
    grantTeamWorkloadPermissions($this->user);

    $userA = User::factory()->create(['company_id' => $this->company->id]);
    $userB = User::factory()->create(['company_id' => $this->company->id]);

    $employeeA = teamWorkloadEmployee($this->company->id, $userA->id);
    $employeeB = teamWorkloadEmployee($this->company->id, $userB->id);

    teamWorkloadTask($this->company->id, $userA->id, ProjectTask::STATUS_TODO);
    teamWorkloadTask($this->company->id, $userA->id, ProjectTask::STATUS_IN_PROGRESS);
    teamWorkloadTask($this->company->id, $userA->id, ProjectTask::STATUS_DONE);

    teamWorkloadTicket($this->company->id, $userA->id, Ticket::STATUS_OPEN);
    teamWorkloadTicket($this->company->id, $userA->id, Ticket::STATUS_CLOSED);

    teamWorkloadTimesheet($this->company->id, $employeeA->id, Timesheet::STATUS_SUBMITTED);
    teamWorkloadTimesheet($this->company->id, $employeeA->id, Timesheet::STATUS_DRAFT);

    teamWorkloadTicket($this->company->id, $userB->id, Ticket::STATUS_PENDING);

    teamWorkloadTimesheet($this->company->id, $employeeB->id, Timesheet::STATUS_SUBMITTED);
    teamWorkloadTimesheet($this->company->id, $employeeB->id, Timesheet::STATUS_APPROVED);

    $otherCompany = Company::factory()->create();
    $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
    $otherEmployee = teamWorkloadEmployee($otherCompany->id, $otherUser->id);
    teamWorkloadTask($otherCompany->id, $otherUser->id, ProjectTask::STATUS_TODO);
    teamWorkloadTicket($otherCompany->id, $otherUser->id, Ticket::STATUS_OPEN);
    teamWorkloadTimesheet($otherCompany->id, $otherEmployee->id, Timesheet::STATUS_SUBMITTED);

    $this->getJson('/api/v1/team-workload')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.employee_id', $employeeA->id)
        ->assertJsonPath('0.employee_name', trim($employeeA->first_name.' '.$employeeA->last_name))
        ->assertJsonPath('0.open_tasks', 2)
        ->assertJsonPath('0.open_tickets', 1)
        ->assertJsonPath('0.pending_timesheets', 1)
        ->assertJsonPath('0.total_load', 4)
        ->assertJsonPath('1.employee_id', $employeeB->id)
        ->assertJsonPath('1.open_tasks', 0)
        ->assertJsonPath('1.open_tickets', 1)
        ->assertJsonPath('1.pending_timesheets', 2)
        ->assertJsonPath('1.total_load', 3);
});

it('summarises the company workload from the seeded data', function () {
    grantTeamWorkloadPermissions($this->user);

    $userA = User::factory()->create(['company_id' => $this->company->id]);
    $userB = User::factory()->create(['company_id' => $this->company->id]);

    $employeeA = teamWorkloadEmployee($this->company->id, $userA->id);
    $employeeB = teamWorkloadEmployee($this->company->id, $userB->id);

    teamWorkloadTask($this->company->id, $userA->id, ProjectTask::STATUS_TODO);
    teamWorkloadTask($this->company->id, $userA->id, ProjectTask::STATUS_IN_PROGRESS);
    teamWorkloadTask($this->company->id, $userA->id, ProjectTask::STATUS_BLOCKED);
    teamWorkloadTicket($this->company->id, $userA->id, Ticket::STATUS_OPEN);
    teamWorkloadTimesheet($this->company->id, $employeeA->id, Timesheet::STATUS_SUBMITTED);
    teamWorkloadTimesheet($this->company->id, $employeeA->id, Timesheet::STATUS_DRAFT);

    teamWorkloadTicket($this->company->id, $userB->id, Ticket::STATUS_PENDING);
    teamWorkloadTicket($this->company->id, $userB->id, Ticket::STATUS_OPEN);
    teamWorkloadTimesheet($this->company->id, $employeeB->id, Timesheet::STATUS_SUBMITTED);
    teamWorkloadTimesheet($this->company->id, $employeeB->id, Timesheet::STATUS_APPROVED);

    $this->getJson('/api/v1/team-workload/summary')
        ->assertOk()
        ->assertJsonPath('total_employees', 2)
        ->assertJsonPath('total_open_tasks', 3)
        ->assertJsonPath('total_open_tickets', 3)
        ->assertJsonPath('total_pending_timesheets', 3)
        ->assertJsonPath('average_load', 4.5);
});

it('summarises an empty team to zero totals', function () {
    grantTeamWorkloadPermissions($this->user);

    $this->getJson('/api/v1/team-workload/summary')
        ->assertOk()
        ->assertJsonPath('total_employees', 0)
        ->assertJsonPath('total_open_tasks', 0)
        ->assertJsonPath('total_open_tickets', 0)
        ->assertJsonPath('total_pending_timesheets', 0)
        ->assertJsonPath('average_load', 0);
});
