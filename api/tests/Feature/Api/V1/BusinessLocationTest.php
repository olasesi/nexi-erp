<?php

use App\Models\BusinessLocation;
use App\Models\Company;
use App\Models\User;
use App\Services\BusinessSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists business locations', function () {
    BusinessLocation::factory(3)->create();

    $response = $this->getJson('/api/v1/business-locations');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows a business location', function () {
    $location = BusinessLocation::factory()->create();

    $response = $this->getJson("/api/v1/business-locations/{$location->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $location->id)
        ->assertJsonPath('data.name', $location->name);
});

it('creates a business location with explicit fields', function () {
    $company = Company::factory()->create();

    $response = $this->postJson('/api/v1/business-locations', [
        'company_id' => $company->id,
        'name' => 'Main Branch',
        'landmark' => 'Linking Street',
        'city' => 'Phoenix',
        'zip_code' => '85001',
        'state' => 'Arizona',
        'country' => 'USA',
        'price_group' => 'p1',
        'invoice_scheme' => 'default',
        'invoice_layout_for_pos' => 'default',
        'invoice_layout_for_sale' => 'default',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.company_id', $company->id)
        ->assertJsonPath('data.name', 'Main Branch')
        ->assertJsonPath('data.city', 'Phoenix')
        ->assertJsonPath('data.state', 'Arizona')
        ->assertJsonPath('data.invoice_scheme', 'default');

    $this->assertDatabaseHas('business_locations', [
        'company_id' => $company->id,
        'name' => 'Main Branch',
        'city' => 'Phoenix',
    ]);
});

it('creates a business location with an auto-generated location id', function () {
    $company = Company::factory()->create();

    $response = $this->postJson('/api/v1/business-locations', [
        'company_id' => $company->id,
        'name' => 'Warehouse A',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['location_id']])
        ->assertJsonPath('data.location_id', 'BL-0001');

    $this->postJson('/api/v1/business-locations', [
        'company_id' => $company->id,
        'name' => 'Warehouse B',
    ])->assertCreated()->assertJsonPath('data.location_id', 'BL-0002');
});

it('uses the configured business settings prefix for generated location ids', function () {
    $company = Company::factory()->create();

    app(BusinessSettingsService::class)->set($company->id, 'prefixes', ['business_location' => 'LOC']);

    $this->postJson('/api/v1/business-locations', [
        'company_id' => $company->id,
        'name' => 'Warehouse A',
    ])->assertCreated()->assertJsonPath('data.location_id', 'LOC-0001');
});

it('updates a business location', function () {
    $location = BusinessLocation::factory()->create();

    $response = $this->putJson("/api/v1/business-locations/{$location->id}", [
        'name' => 'Renamed Branch',
        'zip_code' => '90210',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Renamed Branch')
        ->assertJsonPath('data.zip_code', '90210');

    $this->assertDatabaseHas('business_locations', [
        'id' => $location->id,
        'name' => 'Renamed Branch',
    ]);
});

it('deletes a business location', function () {
    $location = BusinessLocation::factory()->create();

    $response = $this->deleteJson("/api/v1/business-locations/{$location->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($location);
});

it('filters business locations by city', function () {
    $company = Company::factory()->create();

    BusinessLocation::factory()->create(['company_id' => $company->id, 'city' => 'Phoenix']);
    BusinessLocation::factory()->create(['company_id' => $company->id, 'city' => 'Tucson']);

    $response = $this->getJson('/api/v1/business-locations?city=Phoenix');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('city')->unique()->values()->first())->toBe('Phoenix');
});

it('searches business locations by name', function () {
    $company = Company::factory()->create();

    BusinessLocation::factory()->create(['company_id' => $company->id, 'name' => 'Head Office']);
    BusinessLocation::factory()->create(['company_id' => $company->id, 'name' => 'Remote Depot']);

    $response = $this->getJson('/api/v1/business-locations?search=Depot');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('name'))->toContain('Remote Depot');
});

it('validates a required location name', function () {
    $this->postJson('/api/v1/business-locations', [
        'name' => '',
    ])->assertUnprocessable()->assertJsonValidationErrors(['name']);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/business-locations')->assertUnauthorized();
});
