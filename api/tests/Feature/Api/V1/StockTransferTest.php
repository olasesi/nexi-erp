<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists stock transfers', function () {
    StockTransfer::factory(3)->create();

    $response = $this->getJson('/api/v1/stock-transfers');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows a stock transfer', function () {
    $stockTransfer = StockTransfer::factory()->create();

    $response = $this->getJson("/api/v1/stock-transfers/{$stockTransfer->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $stockTransfer->id);
});

it('creates a stock transfer with items', function () {
    $company = Company::factory()->create();
    $fromWarehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $toWarehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $response = $this->postJson('/api/v1/stock-transfers', [
        'company_id' => $company->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 5,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

it('validates different warehouses', function () {
    $company = Company::factory()->create();
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $response = $this->postJson('/api/v1/stock-transfers', [
        'company_id' => $company->id,
        'from_warehouse_id' => $warehouse->id,
        'to_warehouse_id' => $warehouse->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 5,
            ],
        ],
    ]);

    $response->assertStatus(422);
});

it('completes a stock transfer', function () {
    $company = Company::factory()->create();
    $fromWarehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $toWarehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $createResponse = $this->postJson('/api/v1/stock-transfers', [
        'company_id' => $company->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 5,
            ],
        ],
    ]);

    $transferId = $createResponse->json('data.id');

    $response = $this->postJson("/api/v1/stock-transfers/{$transferId}/complete");

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('cancels a stock transfer', function () {
    $company = Company::factory()->create();
    $fromWarehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $toWarehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $createResponse = $this->postJson('/api/v1/stock-transfers', [
        'company_id' => $company->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 5,
            ],
        ],
    ]);

    $transferId = $createResponse->json('data.id');

    $response = $this->postJson("/api/v1/stock-transfers/{$transferId}/cancel");

    $response->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('deletes a pending stock transfer', function () {
    $company = Company::factory()->create();
    $fromWarehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $toWarehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $createResponse = $this->postJson('/api/v1/stock-transfers', [
        'company_id' => $company->id,
        'from_warehouse_id' => $fromWarehouse->id,
        'to_warehouse_id' => $toWarehouse->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 5,
            ],
        ],
    ]);

    $transferId = $createResponse->json('data.id');

    $response = $this->deleteJson("/api/v1/stock-transfers/{$transferId}");

    $response->assertNoContent();
    $this->assertSoftDeleted('stock_transfers', ['id' => $transferId]);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/stock-transfers')->assertUnauthorized();
});
