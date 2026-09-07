<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists quotations', function () {
    Quotation::factory(3)->create();

    $response = $this->getJson('/api/v1/quotations');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows a quotation', function () {
    $quotation = Quotation::factory()->create();

    $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $quotation->id);
});

it('creates a quotation with items', function () {
    $company = Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id]);
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $response = $this->postJson('/api/v1/quotations', [
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 100.00,
            ],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonStructure(['data' => ['items']]);
});

it('validates quotation requires items', function () {
    $company = Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id]);
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);

    $response = $this->postJson('/api/v1/quotations', [
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $response->assertStatus(422);
});

it('accepts a quotation', function () {
    $company = Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id]);
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $createResponse = $this->postJson('/api/v1/quotations', [
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 100.00,
            ],
        ],
    ]);

    $quotationId = $createResponse->json('data.id');

    $response = $this->postJson("/api/v1/quotations/{$quotationId}/accept");

    $response->assertOk()
        ->assertJsonPath('data.status', 'accepted');
});

it('rejects a quotation', function () {
    $company = Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id]);
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $createResponse = $this->postJson('/api/v1/quotations', [
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 100.00,
            ],
        ],
    ]);

    $quotationId = $createResponse->json('data.id');

    $response = $this->postJson("/api/v1/quotations/{$quotationId}/reject");

    $response->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

it('deletes a draft quotation', function () {
    $company = Company::factory()->create();
    $contact = Contact::factory()->create(['company_id' => $company->id]);
    $warehouse = Warehouse::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id]);

    $createResponse = $this->postJson('/api/v1/quotations', [
        'company_id' => $company->id,
        'contact_id' => $contact->id,
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 100.00,
            ],
        ],
    ]);

    $quotationId = $createResponse->json('data.id');

    $response = $this->deleteJson("/api/v1/quotations/{$quotationId}");

    $response->assertNoContent();
    $this->assertSoftDeleted('quotations', ['id' => $quotationId]);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/quotations')->assertUnauthorized();
});
