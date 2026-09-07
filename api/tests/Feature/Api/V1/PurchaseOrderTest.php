<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists purchase orders', function () {
    PurchaseOrder::factory()->create();

    $response = $this->getJson('/api/v1/purchase-orders');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('creates a purchase order with items', function () {
    $company = Company::factory()->create();
    $contact = Contact::factory()->supplier()->create(['company_id' => $company->id]);
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $category = ProductCategory::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create([
        'company_id' => $company->id,
        'category_id' => $category->id,
    ]);

    $response = $this->postJson('/api/v1/purchase-orders', [
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'product_name' => $product->name,
                'product_sku' => $product->sku,
                'quantity' => 10,
                'unit_price' => 25.00,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'order_number', 'items']]);
});

it('shows a purchase order', function () {
    $order = PurchaseOrder::factory()->create();

    $response = $this->getJson("/api/v1/purchase-orders/{$order->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $order->id);
});

it('updates a purchase order', function () {
    $order = PurchaseOrder::factory()->create();

    $response = $this->putJson("/api/v1/purchase-orders/{$order->id}", [
        'notes' => 'Updated notes',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.notes', 'Updated notes');
});

it('deletes a purchase order', function () {
    $order = PurchaseOrder::factory()->create();

    $response = $this->deleteJson("/api/v1/purchase-orders/{$order->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($order);
});

it('filters purchase orders by status', function () {
    PurchaseOrder::factory()->create(['status' => 'draft']);
    PurchaseOrder::factory()->confirmed()->create();

    $response = $this->getJson('/api/v1/purchase-orders?status=draft');

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('status'))->each->toBe('draft');
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/purchase-orders')->assertUnauthorized();
});
