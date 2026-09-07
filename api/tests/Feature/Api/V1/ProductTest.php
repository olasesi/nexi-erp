<?php

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists products', function () {
    Product::factory(3)->create();

    $response = $this->getJson('/api/v1/products');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows a product', function () {
    $product = Product::factory()->create();

    $response = $this->getJson("/api/v1/products/{$product->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $product->id);
});

it('creates a product', function () {
    $company = Company::factory()->create();
    $category = ProductCategory::factory()->create(['company_id' => $company->id]);

    $response = $this->postJson('/api/v1/products', [
        'company_id' => $company->id,
        'category_id' => $category->id,
        'name' => 'Widget Pro',
        'sku' => 'WGT-001',
        'type' => 'product',
        'unit' => 'pcs',
        'sale_price' => 29.99,
        'purchase_price' => 15.00,
        'cost_price' => 10.00,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Widget Pro');
});

it('updates a product', function () {
    $product = Product::factory()->create();

    $response = $this->putJson("/api/v1/products/{$product->id}", [
        'name' => 'Updated Widget',
        'sale_price' => 39.99,
        'purchase_price' => 20.00,
        'cost_price' => 12.00,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Widget');
});

it('deletes a product', function () {
    $product = Product::factory()->create();

    $response = $this->deleteJson("/api/v1/products/{$product->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($product);
});

it('filters products by type', function () {
    Product::factory()->create(['type' => 'product']);
    Product::factory()->service()->create();

    $response = $this->getJson('/api/v1/products?type=product');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('type'))->each->toBe('product');
});

it('searches products by name', function () {
    Product::factory()->create(['name' => 'Widget Alpha']);
    Product::factory()->create(['name' => 'Gadget Beta']);

    $response = $this->getJson('/api/v1/products?search=Widget');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/products')->assertUnauthorized();
});
