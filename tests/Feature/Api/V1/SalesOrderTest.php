<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->withHeader('Authorization', "Bearer $this->token");
});

it('lists sales orders', function () {
    $company = Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id]);
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    \App\Models\SalesOrder::factory()->create([
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $response = $this->getJson('/api/v1/sales-orders');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('creates a sales order with items', function () {
    $company = Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id]);
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

    $category = ProductCategory::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
    ]);

    $response = $this->postJson('/api/v1/sales-orders', [
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => 2,
                'unit_price' => 50.00,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'order_number', 'items']]);
});

it('shows a sales order', function () {
    $order = \App\Models\SalesOrder::factory()->create();

    $response = $this->getJson("/api/v1/sales-orders/{$order->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $order->id);
});

it('updates a sales order', function () {
    $order = \App\Models\SalesOrder::factory()->create();

    $response = $this->putJson("/api/v1/sales-orders/{$order->id}", [
        'notes' => 'Updated notes',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.notes', 'Updated notes');
});

it('deletes a sales order', function () {
    $order = \App\Models\SalesOrder::factory()->create();

    $response = $this->deleteJson("/api/v1/sales-orders/{$order->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($order);
});

it('filters sales orders by status', function () {
    $company = Company::factory()->create();
    \App\Models\SalesOrder::factory()->create(['company_id' => $company->id, 'status' => 'draft']);
    \App\Models\SalesOrder::factory()->confirmed()->create(['company_id' => $company->id]);

    $response = $this->getJson('/api/v1/sales-orders?status=draft');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('status'))->each->toBe('draft');
});

it('requires authentication', function () {
    $this->flushHeaders();

    $this->getJson('/api/v1/sales-orders')->assertUnauthorized();
});
