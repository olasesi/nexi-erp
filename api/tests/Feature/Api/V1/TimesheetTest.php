<?php

use App\Models\Company;
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

function timesheet(array $overrides = []): Timesheet
{
    return Timesheet::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $overrides));
}

function grantTimesheetPermissions(User $user): void
{
    foreach (['timesheets', 'timesheet-entries'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'timesheet%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/timesheets')->assertUnauthorized();
});

it('blocks users without the timesheet permissions', function () {
    foreach (['timesheets', 'timesheet-entries'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/timesheets')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantTimesheetPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/timesheets')->assertOk();

    $this->putJson('/api/v1/addons/timesheet', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/timesheets')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'timesheet');
});

it('creates a timesheet defaulting its status to draft', function () {
    $this->postJson('/api/v1/timesheets', [
        'title' => 'Week 42 Hours',
        'period_start' => now()->startOfWeek()->toDateString(),
        'period_end' => now()->endOfWeek()->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Week 42 Hours')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.company_id', $this->company->id);
});

it('submits a draft timesheet and refuses a second submit', function () {
    $timesheet = timesheet();

    $this->postJson("/api/v1/timesheets/{$timesheet->id}/submit")
        ->assertOk()
        ->assertJsonPath('data.status', 'submitted');

    expect($timesheet->fresh()?->submitted_at)->not->toBeNull();

    $this->postJson("/api/v1/timesheets/{$timesheet->id}/submit")->assertStatus(422);
});

it('cannot approve an unsubmitted timesheet and approves a submitted one', function () {
    $draft = timesheet();

    $this->postJson("/api/v1/timesheets/{$draft->id}/approve")->assertStatus(422);

    $submitted = timesheet();
    $this->postJson("/api/v1/timesheets/{$submitted->id}/submit")->assertOk();

    $this->postJson("/api/v1/timesheets/{$submitted->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($submitted->fresh()?->approved_at)->not->toBeNull();
});

it('rejects a submitted timesheet with a reason', function () {
    $timesheet = timesheet();
    $this->postJson("/api/v1/timesheets/{$timesheet->id}/submit")->assertOk();

    $this->postJson("/api/v1/timesheets/{$timesheet->id}/reject", [
        'rejected_reason' => 'Missing Friday hours.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejected_reason', 'Missing Friday hours.');

    $this->postJson("/api/v1/timesheets/{$timesheet->id}/reject")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['rejected_reason']);
});

it('recalculates total_hours when an entry is added', function () {
    $timesheet = timesheet();

    $this->postJson('/api/v1/timesheet-entries', [
        'timesheet_id' => $timesheet->id,
        'work_date' => now()->toDateString(),
        'hours' => 8,
    ])
        ->assertCreated()
        ->assertJsonPath('data.timesheet_id', $timesheet->id)
        ->assertJsonPath('data.company_id', $this->company->id);

    expect($timesheet->fresh()?->total_hours)->toBe(8.0);
});

it('scopes timesheets to the acting user’s company', function () {
    timesheet();
    timesheet()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/timesheets')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
