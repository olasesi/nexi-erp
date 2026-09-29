<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);

    $this->company = Company::factory()->create();
    $this->contact = Contact::factory()->create(['company_id' => $this->company->id]);
    $category = ProductCategory::factory()->create(['company_id' => $this->company->id]);
    $this->product = Product::factory()->create([
        'company_id' => $this->company->id,
        'category_id' => $category->id,
        'sale_price' => 100.00,
        'tax_rate' => 0,
    ]);
});

it('applies a document-wide discount rate on top of line totals', function () {
    $response = $this->postJson('/api/v1/sales-orders', [
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'discount_rate' => 10,
        'items' => [
            ['product_id' => $this->product->id, 'product_name' => 'Widget', 'quantity' => 2, 'unit_price' => 100.00],
        ],
    ])->assertCreated();

    $response->assertJsonPath('data.subtotal', 200)
        ->assertJsonPath('data.discount_amount', 20)
        ->assertJsonPath('data.discount_rate', 10)
        ->assertJsonPath('data.total', 180);
});

it('combines line discounts with the document discount rate', function () {
    $response = $this->postJson('/api/v1/sales-orders', [
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'discount_rate' => 10,
        'items' => [
            [
                'product_id' => $this->product->id,
                'product_name' => 'Widget',
                'quantity' => 1,
                'unit_price' => 200.00,
                'discount_amount' => 20.00,
            ],
        ],
    ])->assertCreated();

    $response->assertJsonPath('data.subtotal', 200)
        ->assertJsonPath('data.discount_amount', 40)
        ->assertJsonPath('data.total', 160);
});

it('rejects an out-of-range discount rate', function () {
    $this->postJson('/api/v1/sales-orders', [
        'company_id' => $this->company->id,
        'contact_id' => $this->contact->id,
        'discount_rate' => 150,
        'items' => [
            ['product_id' => $this->product->id, 'product_name' => 'Widget', 'quantity' => 1, 'unit_price' => 100.00],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('discount_rate');
});

it('keeps the general ledger balanced when an invoice carries a discount', function () {
    $response = $this->postJson('/api/v1/invoices', [
        'company_id' => $this->company->id,
        'type' => 'invoice',
        'contact_id' => $this->contact->id,
        'issue_date' => now()->toDateString(),
        'discount_rate' => 10,
        'items' => [
            [
                'product_id' => $this->product->id,
                'description' => 'Widget',
                'quantity' => 1,
                'unit_price' => 200.00,
                'tax_rate' => 20,
            ],
        ],
    ])->assertCreated();

    expect((float) $response->json('data.subtotal'))->toBe((float) 200)
        ->and((float) $response->json('data.tax_amount'))->toBe((float) 40)
        ->and((float) $response->json('data.discount_amount'))->toBe((float) 20)
        ->and((float) $response->json('data.total'))->toBe((float) 220);

    $id = $response->json('data.id');

    $this->putJson("/api/v1/invoices/{$id}", ['status' => 'sent'])->assertOk();

    $entry = JournalEntry::where('source_type', Invoice::class)->where('source_id', $id)->first();

    expect($entry)->not->toBeNull();
    expect($entry->lines->sum('debit'))->toBe($entry->lines->sum('credit'));
});
