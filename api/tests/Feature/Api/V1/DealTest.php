<?php

use App\Models\Company;
use App\Models\CrmStage;
use App\Models\Deal;
use App\Models\User;
use App\Support\PermissionGuard;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
    $this->stage = CrmStage::factory()->create(['company_id' => $this->company->id]);
});

it('lists deals with their stage and customer', function () {
    Deal::factory()->count(3)->create(['company_id' => $this->company->id, 'crm_stage_id' => $this->stage->id]);

    $response = $this->getJson('/api/v1/deals');

    $response->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
    expect($response->json('data'))->toHaveCount(3);
});

it('shows a deal', function () {
    $deal = Deal::factory()->create(['company_id' => $this->company->id, 'crm_stage_id' => $this->stage->id]);

    $this->getJson('/api/v1/deals/'.$deal->id)
        ->assertOk()
        ->assertJsonPath('data.stage.id', $this->stage->id)
        ->assertJsonPath('data.title', $deal->title);
});

it('creates a deal on the open pipeline and records who raised it', function () {
    $response = $this->postJson('/api/v1/deals', [
        'company_id' => $this->company->id,
        'crm_stage_id' => $this->stage->id,
        'title' => 'Raman Foods expansion',
        'value' => 12000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.value', 12000)
        ->assertJsonPath('data.title', 'Raman Foods expansion');

    $deal = Deal::where('company_id', $this->company->id)->firstOrFail();

    expect($deal->created_by)->toEqual($this->user->id);
});

it('validates a deal needs a pipeline stage', function () {
    $this->postJson('/api/v1/deals', [
        'company_id' => $this->company->id,
        'title' => 'Orphan deal',
    ])->assertStatus(422)->assertJsonValidationErrors(['crm_stage_id']);
});

it('moves a deal across stages', function () {
    $next = CrmStage::factory()->create(['company_id' => $this->company->id, 'name' => 'Negotiation']);
    $deal = Deal::factory()->create(['company_id' => $this->company->id, 'crm_stage_id' => $this->stage->id]);

    $this->putJson('/api/v1/deals/'.$deal->id, ['crm_stage_id' => $next->id])
        ->assertOk()
        ->assertJsonPath('data.crm_stage_id', $next->id);

    expect($deal->fresh()->crm_stage_id)->toEqual($next->id);
});

it('filters deals by status and stage', function () {
    $won = CrmStage::factory()->create(['company_id' => $this->company->id, 'name' => 'Closed Won']);

    Deal::factory()->count(2)->create(['company_id' => $this->company->id, 'crm_stage_id' => $this->stage->id]);
    Deal::factory()->create(['company_id' => $this->company->id, 'crm_stage_id' => $won->id, 'status' => 'won']);

    expect($this->getJson('/api/v1/deals?status=won')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/deals?crm_stage_id='.$this->stage->id)->json('data'))->toHaveCount(2)
        ->and($this->getJson('/api/v1/deals')->json('data'))->toHaveCount(3);
});

it('searches for deals by title', function () {
    Deal::factory()->create(['company_id' => $this->company->id, 'crm_stage_id' => $this->stage->id, 'title' => 'Harbor refresh contract']);
    Deal::factory()->create(['company_id' => $this->company->id, 'crm_stage_id' => $this->stage->id, 'title' => 'Stillwater maintenance']);

    expect($this->getJson('/api/v1/deals?search=Harbor')->json('data'))->toHaveCount(1);
});

it('closes a deal as won', function () {
    $deal = Deal::factory()->create(['company_id' => $this->company->id, 'crm_stage_id' => $this->stage->id]);

    $this->putJson('/api/v1/deals/'.$deal->id, ['status' => 'won'])
        ->assertOk()
        ->assertJsonPath('data.status', 'won');
});

it('keeps the deal pipeline behind the deals permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $plain = User::factory()->create();
    $plain->assignRole('user');
    Passport::actingAs($plain);

    $this->getJson('/api/v1/deals')->assertForbidden();
    $this->postJson('/api/v1/deals', [
        'company_id' => $this->company->id,
        'crm_stage_id' => $this->stage->id,
        'title' => 'Nope',
    ])->assertForbidden();

    $role = Role::create(['name' => 'closer', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo(['deals.view-any', 'deals.view', 'deals.create', 'deals.update']);

    $closer = User::factory()->create();
    $closer->assignRole($role);
    Passport::actingAs($closer);

    $this->getJson('/api/v1/deals')->assertOk();

    $deal = Deal::factory()->create(['company_id' => $this->company->id, 'crm_stage_id' => $this->stage->id]);
    $this->putJson('/api/v1/deals/'.$deal->id, ['status' => 'won'])->assertOk();
    $this->deleteJson('/api/v1/deals/'.$deal->id)->assertForbidden();
});
