<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);

    $this->company = Company::factory()->create();
    $this->contact = Contact::factory()->create(['company_id' => $this->company->id]);
    $this->warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);
    $category = ProductCategory::factory()->create(['company_id' => $this->company->id]);
    $this->product = Product::factory()->create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'tax_rate' => 0,
    ]);
});

beforeEach(function () {
    $response = $this->postJson('/api/v1/purchase-orders', [
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'confirmed',
        'items' => [
            [
                'product_id' => $this->product->id,
                'product_name' => 'Raw Widget',
                'quantity' => 10,
                'unit_price' => 5.00,
            ],
        ],
    ])->assertCreated();

    $this->purchaseOrder = PurchaseOrder::with('items')->findOrFail($response->json('data.id'));
});

it('receives goods, increments stock and completes the purchase order', function () {
    $this->postJson("/api/v1/purchase-orders/{$this->purchaseOrder->id}/receive", [
        'warehouse_id' => $this->warehouse->id,
        'items' => [
            [
                'purchase_order_item_id' => $this->purchaseOrder->items->first()->id,
                'quantity_received' => 10,
            ],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.receipt_number', PurchaseReceipt::latest('id')->first()->receipt_number)
        ->assertJsonPath('data.items.0.quantity_received', 10);

    $inventory = Inventory::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect((float) $inventory->quantity)->toBe(10.0);
    expect($this->purchaseOrder->fresh()->status)->toBe('delivered');
});

it('supports partial receipts without completing the order', function () {
    $this->postJson("/api/v1/purchase-orders/{$this->purchaseOrder->id}/receive", [
        'items' => [
            [
                'purchase_order_item_id' => $this->purchaseOrder->items->first()->id,
                'quantity_received' => 4,
            ],
        ],
    ])->assertCreated();

    expect($this->purchaseOrder->fresh()->status)->toBe('confirmed');
});

it('rejects receiving more than the outstanding quantity', function () {
    $this->postJson("/api/v1/purchase-orders/{$this->purchaseOrder->id}/receive", [
        'items' => [
            [
                'purchase_order_item_id' => $this->purchaseOrder->items->first()->id,
                'quantity_received' => 12,
            ],
        ],
    ])->assertUnprocessable();
});

it('rejects receiving a draft purchase order', function () {
    $this->purchaseOrder->update(['status' => 'draft']);

    $this->postJson("/api/v1/purchase-orders/{$this->purchaseOrder->id}/receive", [
        'items' => [
            [
                'purchase_order_item_id' => $this->purchaseOrder->items->first()->id,
                'quantity_received' => 1,
            ],
        ],
    ])->assertStatus(422);
});

it('reverses stock when a receipt is deleted and reopens the order', function () {
    $this->postJson("/api/v1/purchase-orders/{$this->purchaseOrder->id}/receive", [
        'items' => [
            [
                'purchase_order_item_id' => $this->purchaseOrder->items->first()->id,
                'quantity_received' => 10,
            ],
        ],
    ])->assertCreated();

    $receipt = PurchaseReceipt::first();

    $this->deleteJson("/api/v1/purchase-receipts/{$receipt->id}")->assertNoContent();

    $inventory = Inventory::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect((float) $inventory->quantity)->toBe(0.0);
    expect($this->purchaseOrder->fresh()->status)->toBe('confirmed');
});
