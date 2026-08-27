<?php

use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->withHeader('Authorization', "Bearer $this->token");
});

it('lists warehouses', function () {
    Warehouse::factory(3)->create();

    $response = $this->getJson('/api/v1/warehouses');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows a warehouse', function () {
    $warehouse = Warehouse::factory()->create();

    $response = $this->getJson("/api/v1/warehouses/{$warehouse->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $warehouse->id);
});

it('creates a warehouse', function () {
    $company = Company::factory()->create();

    $response = $this->postJson('/api/v1/warehouses', [
        'company_id' => $company->id,
        'name' => 'Main Warehouse',
        'code' => 'WH-MAIN-001',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Main Warehouse');
});

it('updates a warehouse', function () {
    $warehouse = Warehouse::factory()->create();

    $response = $this->putJson("/api/v1/warehouses/{$warehouse->id}", [
        'name' => 'Updated Warehouse',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Warehouse');
});

it('deletes a warehouse', function () {
    $warehouse = Warehouse::factory()->create();

    $response = $this->deleteJson("/api/v1/warehouses/{$warehouse->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($warehouse);
});

it('filters warehouses by is_active', function () {
    Warehouse::factory()->create(['is_active' => true]);
    Warehouse::factory()->inactive()->create();

    $response = $this->getJson('/api/v1/warehouses?is_active=1');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('is_active'))->each->toBeTrue();
});

it('searches warehouses by name', function () {
    Warehouse::factory()->create(['name' => 'Main Warehouse']);
    Warehouse::factory()->create(['name' => 'Secondary Warehouse']);

    $response = $this->getJson('/api/v1/warehouses?search=Main');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

it('requires authentication', function () {
    $this->flushHeaders();

    $this->getJson('/api/v1/warehouses')->assertUnauthorized();
});
