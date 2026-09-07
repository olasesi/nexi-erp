<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists stock adjustments', function () {
    StockAdjustment::factory(3)->create();

    $response = $this->getJson('/api/v1/stock-adjustments');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows a stock adjustment', function () {
    $stockAdjustment = StockAdjustment::factory()->create();

    $response = $this->getJson("/api/v1/stock-adjustments/{$stockAdjustment->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $stockAdjustment->id);
});

it('creates a stock adjustment with items', function () {
    $company = Company::factory()->create();
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $response = $this->postJson('/api/v1/stock-adjustments', [
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'reason' => 'Annual stock count correction',
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 10,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['items']]);
});

it('validates stock adjustment requires items', function () {
    $company = Company::factory()->create();
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

    $response = $this->postJson('/api/v1/stock-adjustments', [
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'reason' => 'Missing items',
    ]);

    $response->assertStatus(422);
});

it('deletes a stock adjustment', function () {
    $stockAdjustment = StockAdjustment::factory()->create();

    $response = $this->deleteJson("/api/v1/stock-adjustments/{$stockAdjustment->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($stockAdjustment);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/stock-adjustments')->assertUnauthorized();
});
