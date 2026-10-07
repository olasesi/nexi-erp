<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\CrmStage;
use App\Models\Deal;
use App\Models\Lead;
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

it('lists leads with their owner and deals', function () {
    Lead::factory()->count(3)->create(['company_id' => $this->company->id]);

    $response = $this->getJson('/api/v1/leads');

    $response->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
    expect($response->json('data'))->toHaveCount(3);
});

it('shows a lead', function () {
    $lead = Lead::factory()->create(['company_id' => $this->company->id]);

    $this->getJson('/api/v1/leads/'.$lead->id)
        ->assertOk()
        ->assertJsonPath('data.id', $lead->id);
});

it('creates a lead with funnel defaults and records who raised it', function () {
    $response = $this->postJson('/api/v1/leads', [
        'company_id' => $this->company->id,
        'name' => 'Dana Fowler',
        'organization' => 'Fowler Logistics',
        'email' => 'dana@fowler.example',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'new')
        ->assertJsonPath('data.source', 'other')
        ->assertJsonPath('data.organization', 'Fowler Logistics');

    $lead = Lead::where('company_id', $this->company->id)->firstOrFail();

    expect($lead->created_by)->toEqual($this->user->id);
});

it('validates a lead name is required', function () {
    $response = $this->postJson('/api/v1/leads', [
        'company_id' => $this->company->id,
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('rejects an unknown lead source or funnel status', function () {
    $this->postJson('/api/v1/leads', [
        'company_id' => $this->company->id,
        'name' => 'Bad Source',
        'source' => 'telepathy',
    ])->assertStatus(422)->assertJsonValidationErrors(['source']);

    $this->postJson('/api/v1/leads', [
        'company_id' => $this->company->id,
        'name' => 'Bad Status',
        'status' => 'winning',
    ])->assertStatus(422)->assertJsonValidationErrors(['status']);
});

it('filters leads by funnel status, source and owner', function () {
    $owner = User::factory()->create();

    Lead::factory()->qualified()->create(['company_id' => $this->company->id, 'source' => 'call']);
    Lead::factory()->create(['company_id' => $this->company->id, 'source' => 'referral', 'status' => 'new']);
    Lead::factory()->create([
        'company_id' => $this->company->id,
        'source' => 'referral',
        'status' => 'new',
        'assigned_to' => $owner->id,
    ]);

    expect($this->getJson('/api/v1/leads?status=new')->json('data'))->toHaveCount(2)
        ->and($this->getJson('/api/v1/leads?source=referral')->json('data'))->toHaveCount(2)
        ->and($this->getJson('/api/v1/leads?assigned_to='.$owner->id)->json('data'))->toHaveCount(1);
});

it('searches leads by name, organisation, email and phone', function () {
    Lead::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Marisol Vega',
        'organization' => 'Vega Optics',
        'email' => 'marisol@vega.example',
        'phone' => '+1 555 0142',
    ]);
    Lead::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Unrelated Lead',
        'organization' => 'Hollow Works',
        'email' => 'nobody@hollow.example',
        'phone' => '+1 555 0199',
    ]);

    // "Vega" lights up one row through two searchable fields at once.
    expect($this->getJson('/api/v1/leads?search=Vega')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/leads?search=marisol@vega')->json('data'))->toHaveCount(1)
        ->and($this->getJson('/api/v1/leads?search=0142')->json('data'))->toHaveCount(1);
});

it('updates a lead through the funnel', function () {
    $lead = Lead::factory()->create(['company_id' => $this->company->id, 'status' => 'new']);

    $this->putJson('/api/v1/leads/'.$lead->id, ['status' => 'qualified'])
        ->assertOk()
        ->assertJsonPath('data.status', 'qualified');

    expect($lead->fresh()->status)->toBe('qualified');
});

it('converts a lead into a contact sitting on the pipeline', function () {
    $stage = CrmStage::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Qualified',
        'position' => 1,
    ]);
    $lead = Lead::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Priya Raman',
        'organization' => 'Raman Foods',
        'status' => 'qualified',
        'value' => 4200,
    ]);

    $response = $this->postJson('/api/v1/leads/'.$lead->id.'/convert');

    $response->assertOk()
        ->assertJsonPath('data.status', 'converted')
        ->assertJsonStructure(['data' => ['contact', 'deals']]);

    $contact = Contact::find($response->json('data.contact_id'));

    expect($contact->first_name)->toBe('Priya')
        ->and($contact->last_name)->toBe('Raman')
        ->and($contact->type)->toBe('lead');

    $deal = Deal::where('lead_id', $lead->id)->firstOrFail();

    expect($deal->crm_stage_id)->toEqual($stage->id)
        ->and($deal->title)->toBe('Raman Foods')
        ->and($deal->status)->toBe('open')
        ->and((float) $deal->value)->toBe(4200.0)
        ->and($deal->contact_id)->toEqual($contact->id);

    expect($lead->fresh()->converted_at)->not->toBeNull();
});

it('falls back to the organisation when a lead has a single-word name', function () {
    CrmStage::factory()->create(['company_id' => $this->company->id]);
    $lead = Lead::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Acme',
        'organization' => 'Acme Industrial',
        'status' => 'qualified',
    ]);

    $contactId = $this->postJson('/api/v1/leads/'.$lead->id.'/convert')
        ->assertOk()
        ->json('data.contact_id');

    $contact = Contact::findOrFail($contactId);

    expect($contact->first_name)->toBe('Acme')
        ->and($contact->last_name)->toBe('Acme Industrial');
});

it('reuses the contact and deal when a lead is converted twice', function () {
    CrmStage::factory()->create(['company_id' => $this->company->id]);
    $lead = Lead::factory()->create(['company_id' => $this->company->id, 'status' => 'qualified']);

    $first = $this->postJson('/api/v1/leads/'.$lead->id.'/convert')->assertOk();
    $second = $this->postJson('/api/v1/leads/'.$lead->id.'/convert');

    $second->assertStatus(422)
        ->assertJsonPath('message', 'Lead is already converted.');

    expect(Contact::count())->toBe(1)
        ->and(Deal::count())->toBe(1)
        ->and($first->json('data.contact_id'))->not->toBeNull();
});

it('refuses to convert a lost lead', function () {
    CrmStage::factory()->create(['company_id' => $this->company->id]);
    $lead = Lead::factory()->lost()->create(['company_id' => $this->company->id]);

    $this->postJson('/api/v1/leads/'.$lead->id.'/convert')
        ->assertStatus(422)
        ->assertJsonPath('message', 'A lost lead cannot be converted.');

    expect(Contact::count())->toBe(0);
});

it('refuses to convert before the pipeline exists', function () {
    $lead = Lead::factory()->create(['company_id' => $this->company->id, 'status' => 'qualified']);

    $this->postJson('/api/v1/leads/'.$lead->id.'/convert')
        ->assertStatus(422)
        ->assertJsonPath('message', 'Create a pipeline stage before converting a lead.');

    expect($lead->fresh()->status)->toBe('qualified');
});

it('soft deletes a lead', function () {
    $lead = Lead::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson('/api/v1/leads/'.$lead->id)->assertNoContent();

    expect(Lead::find($lead->id))->toBeNull()
        ->and(Lead::withTrashed()->find($lead->id))->not->toBeNull();
});

it('keeps the lead funnel behind the leads permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $plain = User::factory()->create();
    $plain->assignRole('user');
    Passport::actingAs($plain);

    $this->getJson('/api/v1/leads')->assertForbidden();
    $this->postJson('/api/v1/leads', ['company_id' => $this->company->id, 'name' => 'Nope'])
        ->assertForbidden();

    $role = Role::create(['name' => 'sdr', 'guard_name' => PermissionGuard::name()]);
    $role->givePermissionTo(['leads.view-any', 'leads.view', 'leads.create', 'leads.update']);

    $sdr = User::factory()->create();
    $sdr->assignRole($role);
    Passport::actingAs($sdr);

    CrmStage::factory()->create(['company_id' => $this->company->id]);
    $lead = Lead::factory()->create(['company_id' => $this->company->id, 'status' => 'qualified']);

    $this->getJson('/api/v1/leads')->assertOk();
    $this->putJson('/api/v1/leads/'.$lead->id, ['status' => 'contacted'])->assertOk();

    // Converting rides leads.update rather than a privilege of its own.
    $this->postJson('/api/v1/leads/'.$lead->id.'/convert')->assertOk();
    $this->deleteJson('/api/v1/leads/'.$lead->id)->assertForbidden();

    // A reader may study the funnel but never promote out of it.
    $readerRole = Role::create(['name' => 'sales-analyst', 'guard_name' => PermissionGuard::name()]);
    $readerRole->givePermissionTo(['leads.view-any', 'leads.view']);

    $reader = User::factory()->create();
    $reader->assignRole($readerRole);
    Passport::actingAs($reader);

    $promotable = Lead::factory()->create(['company_id' => $this->company->id, 'status' => 'qualified']);

    $this->getJson('/api/v1/leads')->assertOk();
    $this->postJson('/api/v1/leads/'.$promotable->id.'/convert')->assertForbidden();
});
