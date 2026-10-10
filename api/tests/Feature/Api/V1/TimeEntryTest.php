<?php

use App\Models\Company;
use App\Models\TimeEntry;
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

function timeEntry(array $overrides = []): TimeEntry
{
    return TimeEntry::factory()->create(array_merge([
        'company_id' => test()->company->id,
    ], $overrides));
}

function grantTimeEntryPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "time-entries.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'time-entries.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/time-entries')->assertUnauthorized();
});

it('blocks users without the time entry permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create([
            'name' => "time-entries.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/time-entries')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantTimeEntryPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/time-entries')->assertOk();

    $this->putJson('/api/v1/addons/time-tracker', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/time-entries')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'time-tracker');
});

it('creates an entry defaulting its start time and billable flag', function () {
    $this->postJson('/api/v1/time-entries', [
        'task' => 'Write documentation',
    ])
        ->assertCreated()
        ->assertJsonPath('data.task', 'Write documentation')
        ->assertJsonPath('data.is_billable', false)
        ->assertJsonPath('data.company_id', $this->company->id);

    expect(TimeEntry::first()?->started_at)->not->toBeNull();
});

it('refuses to start an already running timer and starts a stopped one', function () {
    $running = TimeEntry::factory()->running()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/time-entries/{$running->id}/start")
        ->assertStatus(422)
        ->assertJsonPath('message', 'This timer is already running.');

    $stopped = timeEntry();

    $this->postJson("/api/v1/time-entries/{$stopped->id}/start")->assertOk();

    expect($stopped->fresh()->ended_at)->toBeNull()
        ->and($stopped->fresh()->duration_minutes)->toBeNull()
        ->and($stopped->fresh()->started_at)->not->toBeNull();
});

it('stops a running timer computing the duration and refuses a stopped one', function () {
    $entry = timeEntry([
        'started_at' => now()->subMinutes(90),
        'ended_at' => null,
        'duration_minutes' => null,
    ]);

    $this->postJson("/api/v1/time-entries/{$entry->id}/stop")
        ->assertOk()
        ->assertJsonPath('data.duration_minutes', 90);

    expect($entry->fresh()->ended_at)->not->toBeNull();

    $stopped = timeEntry();

    $this->postJson("/api/v1/time-entries/{$stopped->id}/stop")
        ->assertStatus(422)
        ->assertJsonPath('message', 'This timer is not running.');
});

it('filters time entries by the billable flag', function () {
    timeEntry(['is_billable' => true]);
    timeEntry(['is_billable' => false]);

    $this->getJson('/api/v1/time-entries?is_billable=1')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.is_billable', true);
});

it('scopes time entries to the acting user’s company', function () {
    timeEntry();
    timeEntry(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/time-entries')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
