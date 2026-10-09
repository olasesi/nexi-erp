<?php

use App\Models\Company;
use App\Models\GymMember;
use App\Models\GymPlan;
use App\Models\GymVisit;
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

function gymPlan(): GymPlan
{
    return GymPlan::factory()->create(['company_id' => test()->company->id]);
}

function gymMember(?GymPlan $plan = null): GymMember
{
    return GymMember::factory()->create([
        'company_id' => test()->company->id,
        'plan_id' => ($plan ?? gymPlan())->id,
    ]);
}

function grantGymPermissions(User $user): void
{
    foreach (['gym-plans', 'gym-members', 'gym-visits'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::firstOrCreate([
                'name' => "{$module}.{$action}",
                'guard_name' => PermissionGuard::name(),
            ]);
        }
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'gym-%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/gym-members')->assertUnauthorized();
});

it('blocks users without the gym permissions', function () {
    foreach (['gym-plans', 'gym-members', 'gym-visits'] as $module) {
        foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
            Permission::create(['name' => "{$module}.{$action}", 'guard_name' => PermissionGuard::name()]);
        }
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/gym-members')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantGymPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/gym-members')->assertOk();

    $this->putJson('/api/v1/addons/gym-management', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/gym-members')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'gym-management');
});

it('creates a plan and validates its billing period', function () {
    $this->postJson('/api/v1/gym-plans', [
        'name' => 'Gold',
        'billing_period' => 'quarterly',
        'price' => 120,
        'max_monthly_visits' => 12,
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Gold')
        ->assertJsonPath('data.billing_period', 'quarterly')
        ->assertJsonPath('data.price', 120)
        ->assertJsonPath('data.max_monthly_visits', 12)
        ->assertJsonPath('data.company_id', $this->company->id);

    $this->postJson('/api/v1/gym-plans', [
        'name' => 'Bad',
        'billing_period' => 'weekly',
        'price' => 10,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['billing_period']);
});

it('registers a member with a generated number and default active status', function () {
    $plan = gymPlan();

    $this->postJson('/api/v1/gym-members', [
        'plan_id' => $plan->id,
        'full_name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'start_at' => now()->toDateString(),
        'renews_at' => now()->addMonth()->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.member_number', 'GM-'.date('y').'-0001')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.effective_status', 'active')
        ->assertJsonPath('data.plan.id', $plan->id)
        ->assertJsonPath('data.company_id', $this->company->id);

    $this->postJson('/api/v1/gym-members', [
        'plan_id' => $plan->id,
        'full_name' => 'Grace Hopper',
        'start_at' => now()->toDateString(),
        'renews_at' => now()->addMonth()->toDateString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.member_number', 'GM-'.date('y').'-0002');
});

it('checks a live member in and records the operator', function () {
    $member = gymMember();

    $this->postJson('/api/v1/gym-visits', ['gym_member_id' => $member->id])
        ->assertCreated()
        ->assertJsonPath('data.gym_member_id', $member->id)
        ->assertJsonPath('data.checked_in_by', $this->user->id)
        ->assertJsonPath('data.checked_out_at', null)
        ->assertJsonPath('data.duration_minutes', null);
});

it('refuses check-in for an expired or suspended member', function () {
    $expired = gymMember(GymPlan::factory()->create([
        'company_id' => $this->company->id,
    ]));
    $expired->update(['renews_at' => now()->subDays(4)->toDateString()]);

    $this->postJson('/api/v1/gym-visits', ['gym_member_id' => $expired->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gym_member_id']);

    $suspended = gymMember();
    $suspended->update(['status' => GymMember::STATUS_SUSPENDED]);

    $this->postJson('/api/v1/gym-visits', ['gym_member_id' => $suspended->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gym_member_id']);
});

it('enforces a plan’s monthly visit cap', function () {
    $plan = GymPlan::factory()->capped(2)->create(['company_id' => $this->company->id]);
    $member = gymMember($plan);

    GymVisit::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'gym_member_id' => $member->id,
        'checked_in_at' => now(),
    ]);

    $this->postJson('/api/v1/gym-visits', ['gym_member_id' => $member->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gym_member_id']);

    $fresh = gymMember($plan);
    GymVisit::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'gym_member_id' => $fresh->id,
        'checked_in_at' => now()->subMonth(),
    ]);

    $this->postJson('/api/v1/gym-visits', ['gym_member_id' => $fresh->id])
        ->assertCreated();
});

it('checks a visit out once and reports its duration', function () {
    $member = gymMember();
    $visit = GymVisit::factory()->create([
        'company_id' => $this->company->id,
        'gym_member_id' => $member->id,
        'checked_in_at' => now()->subHour(),
        'checked_out_at' => null,
    ]);

    $this->postJson("/api/v1/gym-visits/{$visit->id}/check-out")
        ->assertOk()
        ->assertJsonPath('data.gym_member_id', $member->id)
        ->assertJsonPath('data.checked_out_at', fn ($value) => $value !== null)
        ->assertJsonPath('data.duration_minutes', fn ($value) => $value >= 59);

    $this->postJson("/api/v1/gym-visits/{$visit->id}/check-out")->assertStatus(422);
});

it('suspends and reactivates a member but never a cancelled one', function () {
    $member = gymMember();

    $this->postJson("/api/v1/gym-members/{$member->id}/suspend")
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    $this->postJson("/api/v1/gym-members/{$member->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $cancelled = gymMember();
    $cancelled->update(['status' => GymMember::STATUS_CANCELLED]);

    $this->postJson("/api/v1/gym-members/{$cancelled->id}/suspend")->assertStatus(422);
    $this->postJson("/api/v1/gym-members/{$cancelled->id}/activate")->assertStatus(422);
});

it('summarises the membership book and the floor', function () {
    $member = gymMember();
    gymMember()->update(['status' => GymMember::STATUS_SUSPENDED]);
    gymMember()->update(['renews_at' => now()->subDay()->toDateString()]);

    GymVisit::factory()->create([
        'company_id' => $this->company->id,
        'gym_member_id' => $member->id,
        'checked_in_at' => now(),
        'checked_out_at' => null,
    ]);

    $this->getJson('/api/v1/gym-members/summary')
        ->assertOk()
        ->assertJsonPath('summary.active', 1)
        ->assertJsonPath('summary.suspended', 1)
        ->assertJsonPath('summary.expired', 1)
        ->assertJsonPath('summary.visits_today', 1)
        ->assertJsonPath('summary.currently_in', 1);
});

it('keeps open visits but deletes closed ones', function () {
    $member = gymMember();

    $open = GymVisit::factory()->create([
        'company_id' => $this->company->id,
        'gym_member_id' => $member->id,
        'checked_out_at' => null,
    ]);

    $this->deleteJson("/api/v1/gym-visits/{$open->id}")->assertStatus(422);

    $closed = GymVisit::factory()->closed()->create([
        'company_id' => $this->company->id,
        'gym_member_id' => $member->id,
    ]);

    $this->deleteJson("/api/v1/gym-visits/{$closed->id}")->assertNoContent();
});

it('scopes members to the acting user’s company', function () {
    gymMember();
    gymMember()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/gym-members')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
