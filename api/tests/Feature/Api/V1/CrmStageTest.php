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
});

it('lists pipeline stages in position order', function () {
    CrmStage::factory()->create(['company_id' => $this->company->id, 'name' => 'Proposal', 'position' => 2]);
    CrmStage::factory()->create(['company_id' => $this->company->id, 'name' => 'Qualified', 'position' => 1]);

    $response = $this->getJson('/api/v1/crm-stages');

    $response->assertOk()->assertJsonStructure(['data', 'meta', 'links']);

    expect($response->json('data.0.name'))->toBe('Qualified')
        ->and($response->json('data.1.name'))->toBe('Proposal');
});

it('shows how many deals sit on each stage', function () {
    $stage = CrmStage::factory()->create(['company_id' => $this->company->id]);
    Deal::factory()->count(2)->create([
        'company_id' => $this->company->id,
        'crm_stage_id' => $stage->id,
    ]);

    $response = $this->getJson('/api/v1/crm-stages/'.$stage->id);

    $response->assertOk()->assertJsonPath('data.deals_count', 2);
});

it('appends a new stage to the end of the pipeline when no position is given', function () {
    CrmStage::factory()->create(['company_id' => $this->company->id, 'position' => 5]);

    $response = $this->postJson('/api/v1/crm-stages', [
        'company_id' => $this->company->id,
        'name' => 'Negotiation',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Negotiation')
        ->assertJsonPath('data.position', 6);
});

it('validates a stage name is required', function () {
    $response = $this->postJson('/api/v1/crm-stages', [
        'company_id' => $this->company->id,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('keeps stage names unique per company', function () {
    $otherCompany = Company::factory()->create();

    CrmStage::factory()->create(['company_id' => $this->company->id, 'name' => 'Closed']);
    CrmStage::factory()->create(['company_id' => $otherCompany->id, 'name' => 'Closed']);

    $response = $this->postJson('/api/v1/crm-stages', [
        'company_id' => $this->company->id,
        'name' => 'Closed',
    ]);

    $response->assertStatus(500);
});

it('refuses to delete a stage that still carries deals', function () {
    $stage = CrmStage::factory()->create(['company_id' => $this->company->id]);
    Deal::factory()->create([
        'company_id' => $this->company->id,
        'crm_stage_id' => $stage->id,
    ]);

    $this->deleteJson('/api/v1/crm-stages/'.$stage->id)->assertStatus(422);

    expect(CrmStage::find($stage->id))->not->toBeNull();
});

it('deletes an empty stage', function () {
    $stage = CrmStage::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson('/api/v1/crm-stages/'.$stage->id)->assertNoContent();

    expect(CrmStage::find($stage->id))->toBeNull();
});

it('keeps the pipeline behind the crm-stages permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $plain = User::factory()->create();
    $plain->assignRole('user');
    Passport::actingAs($plain);

    $this->getJson('/api/v1/crm-stages')->assertForbidden();
    $this->postJson('/api/v1/crm-stages', ['company_id' => $this->company->id, 'name' => 'Nope'])
        ->assertForbidden();

    $role = Role::create(['name' => 'pipeline-editor', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo(['crm-stages.view-any', 'crm-stages.view', 'crm-stages.create']);

    $editor = User::factory()->create();
    $editor->assignRole($role);
    Passport::actingAs($editor);

    $this->getJson('/api/v1/crm-stages')->assertOk();
    $this->postJson('/api/v1/crm-stages', ['company_id' => $this->company->id, 'name' => 'Closed'])
        ->assertCreated();

    // Create rights do not imply the right to reshape the pipeline.
    $this->deleteJson('/api/v1/crm-stages/'.CrmStage::latest('id')->first()->id)->assertForbidden();
});
