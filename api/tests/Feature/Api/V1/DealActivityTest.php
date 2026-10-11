<?php

use App\Models\Company;
use App\Models\Deal;
use App\Models\DealActivity;
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

function aDeal(): Deal
{
    return Deal::factory()->create(['company_id' => test()->company->id]);
}

function dealActivity(?Deal $deal = null): DealActivity
{
    return DealActivity::factory()->create([
        'company_id' => test()->company->id,
        'deal_id' => ($deal ?? aDeal())->id,
    ]);
}

function grantDealActivityPermissions(User $user): void
{
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate([
            'name' => "deal-activities.{$action}",
            'guard_name' => PermissionGuard::name(),
        ]);
    }

    $user->givePermissionTo(Permission::where('name', 'like', 'deal-activities.%')->get());
}

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/deal-activities')->assertUnauthorized();
});

it('blocks users without the deal-activity permissions', function () {
    foreach (['view-any', 'view', 'create', 'update', 'delete'] as $action) {
        Permission::create(['name' => "deal-activities.{$action}", 'guard_name' => PermissionGuard::name()]);
    }

    Passport::actingAs(User::factory()->create(['company_id' => $this->company->id]));

    $this->getJson('/api/v1/deal-activities')->assertForbidden();
});

it('hides the whole module when the add-on is switched off', function () {
    grantDealActivityPermissions($this->user);
    Permission::create(['name' => 'addons.update', 'guard_name' => PermissionGuard::name()]);
    $this->user->givePermissionTo('addons.update');

    $this->getJson('/api/v1/deal-activities')->assertOk();

    $this->putJson('/api/v1/addons/deal-activity', ['enabled' => false])->assertOk();

    $this->getJson('/api/v1/deal-activities')
        ->assertForbidden()
        ->assertJsonPath('code', 'addon_disabled')
        ->assertJsonPath('addon', 'deal-activity');
});

it('creates an activity defaulting its type to note and status to pending', function () {
    $deal = aDeal();

    $this->postJson('/api/v1/deal-activities', [
        'deal_id' => $deal->id,
        'title' => 'Initial call with client',
        'scheduled_at' => now()->addDay()->toDateTimeString(),
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Initial call with client')
        ->assertJsonPath('data.type', 'note')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.company_id', $this->company->id)
        ->assertJsonPath('data.deal.id', $deal->id);
});

it('completes a pending activity and refuses to complete it twice', function () {
    $activity = dealActivity();

    $this->postJson("/api/v1/deal-activities/{$activity->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $this->assertNotNull($activity->fresh()->completed_at);

    $this->postJson("/api/v1/deal-activities/{$activity->id}/complete")
        ->assertStatus(422)
        ->assertJsonPath('message', 'Only a pending activity can be completed.');
});

it('filters activities by status', function () {
    dealActivity();
    $completed = dealActivity();
    $this->postJson("/api/v1/deal-activities/{$completed->id}/complete")->assertOk();

    $this->getJson('/api/v1/deal-activities?status=pending')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'pending');
});

it('filters activities by deal', function () {
    $deal = aDeal();
    dealActivity($deal)->update(['title' => 'Follow-up on proposal']);
    dealActivity();

    $this->getJson("/api/v1/deal-activities?deal_id={$deal->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.deal_id', $deal->id)
        ->assertJsonPath('data.0.title', 'Follow-up on proposal');
});

it('scopes activities to the acting user’s company', function () {
    dealActivity();
    dealActivity()->update(['company_id' => Company::factory()->create()->id]);

    $this->getJson('/api/v1/deal-activities')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
