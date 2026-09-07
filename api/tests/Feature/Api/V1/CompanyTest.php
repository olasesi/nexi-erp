<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists companies', function () {
    Company::factory(3)->create();

    $response = $this->getJson('/api/v1/companies');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows a company', function () {
    $company = Company::factory()->create();

    $response = $this->getJson("/api/v1/companies/{$company->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $company->id);
});

it('creates a company', function () {
    $data = [
        'name' => 'Test Corp',
        'legal_name' => 'Test Corp LLC',
        'email' => 'corp@test.com',
        'is_active' => true,
    ];

    $response = $this->postJson('/api/v1/companies', $data);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Test Corp')
        ->assertJsonStructure(['data' => ['id', 'name']]);
});

it('updates a company', function () {
    $company = Company::factory()->create();

    $response = $this->putJson("/api/v1/companies/{$company->id}", [
        'name' => 'Updated Corp',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Corp');
});

it('deletes a company', function () {
    $company = Company::factory()->create();

    $response = $this->deleteJson("/api/v1/companies/{$company->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($company);
});

it('filters companies by is_active', function () {
    Company::factory()->create(['is_active' => true]);
    Company::factory()->inactive()->create();

    $response = $this->getJson('/api/v1/companies?is_active=1');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('is_active'))->each->toBeTrue();
});

it('searches companies by name', function () {
    Company::factory()->create(['name' => 'Acme Corp']);
    Company::factory()->create(['name' => 'Other Inc']);

    $response = $this->getJson('/api/v1/companies?search=Acme');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/companies')->assertUnauthorized();
});
