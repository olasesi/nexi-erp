<?php

use App\Models\Company;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists product categories', function () {
    ProductCategory::factory(3)->create();

    $response = $this->getJson('/api/v1/product-categories');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows a product category', function () {
    $category = ProductCategory::factory()->create();

    $response = $this->getJson("/api/v1/product-categories/{$category->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $category->id);
});

it('creates a product category', function () {
    $company = Company::factory()->create();

    $response = $this->postJson('/api/v1/product-categories', [
        'company_id' => $company->id,
        'name' => 'Electronics',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Electronics');
});

it('updates a product category', function () {
    $category = ProductCategory::factory()->create();

    $response = $this->putJson("/api/v1/product-categories/{$category->id}", [
        'name' => 'Updated Category',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Category');
});

it('deletes a product category', function () {
    $category = ProductCategory::factory()->create();

    $response = $this->deleteJson("/api/v1/product-categories/{$category->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($category);
});

it('filters product categories by is_active', function () {
    ProductCategory::factory()->create(['is_active' => true]);
    ProductCategory::factory()->inactive()->create();

    $response = $this->getJson('/api/v1/product-categories?is_active=1');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('is_active'))->each->toBeTrue();
});

it('searches product categories by name', function () {
    ProductCategory::factory()->create(['name' => 'Electronics']);
    ProductCategory::factory()->create(['name' => 'Furniture']);

    $response = $this->getJson('/api/v1/product-categories?search=Electro');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/product-categories')->assertUnauthorized();
});
