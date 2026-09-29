<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
});

it('imports contacts from inline csv, creating and updating rows', function () {
    Contact::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Existing',
        'last_name' => 'Person',
        'email' => 'existing@example.com',
    ]);

    $csv = <<<'CSV'
first_name,last_name,email,phone,type,notes
Existing,Person,existing@example.com,555-0100,customer,Updated via import
New,Person,new@example.com,555-0101,supplier,
CSV;

    $this->postJson('/api/v1/contacts/import', [
        'company_id' => $this->company->id,
        'csv' => $csv,
    ])->assertOk()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('updated', 1)
        ->assertJsonPath('failed', 0)
        ->assertJsonPath('dry_run', false);

    expect(Contact::where('company_id', $this->company->id)->count())->toBe(2);

    $updated = Contact::where('email', 'existing@example.com')->first();

    expect($updated->notes)->toBe('Updated via import');
});

it('reports row level errors for invalid contact rows', function () {
    $csv = <<<'CSV'
first_name,last_name,email,phone,type,notes
,Person,,,customer,
Ada,Lovelace,not-an-email,,customer,
Grace,Hopper,grace@example.com,,wizard,
CSV;

    $this->postJson('/api/v1/contacts/import', [
        'company_id' => $this->company->id,
        'csv' => $csv,
    ])->assertOk()
        ->assertJsonPath('created', 0)
        ->assertJsonPath('failed', 3)
        ->assertJsonPath('errors.0.line', 2)
        ->assertJsonPath('errors.1.message', 'email must be a valid email address.')
        ->assertJsonPath('errors.2.message', 'type must be customer, supplier or both.');

    expect(Contact::count())->toBe(0);
});

it('rejects a csv payload that is missing required columns', function () {
    $this->postJson('/api/v1/contacts/import', [
        'company_id' => $this->company->id,
        'csv' => "name,email\nAda,ada@example.com\n",
    ])->assertOk()
        ->assertJsonPath('failed', 1)
        ->assertJsonPath('errors.0.message', 'Missing required columns: first_name, last_name, phone, type, notes.');

    expect(Contact::count())->toBe(0);
});

it('validates a dry run without persisting anything', function () {
    $csv = <<<'CSV'
first_name,last_name,email,phone,type,notes
Dry,Run,dry@example.com,,customer,
CSV;

    $this->postJson('/api/v1/contacts/import', [
        'company_id' => $this->company->id,
        'csv' => $csv,
        'dry_run' => true,
    ])->assertOk()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('dry_run', true);

    expect(Contact::count())->toBe(0);
});

it('imports contacts from an uploaded csv file', function () {
    $file = UploadedFile::fake()->createWithContent(
        'contacts.csv',
        "first_name,last_name,email,phone,type,notes\nUpload,Row,upload@example.com,,customer,\n"
    );

    $this->post('/api/v1/contacts/import', [
        'company_id' => $this->company->id,
        'file' => $file,
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('created', 1);

    expect(Contact::where('email', 'upload@example.com')->exists())->toBeTrue();
});

it('requires a csv payload or upload', function () {
    $this->postJson('/api/v1/contacts/import', [
        'company_id' => $this->company->id,
    ])->assertUnprocessable();
});

it('imports and updates products keyed by sku', function () {
    Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Old Widget',
        'sku' => 'WID-1',
    ]);

    $csv = <<<'CSV'
name,sku,barcode,type,unit,sale_price,purchase_price,stock_quantity,min_stock_level
New Widget,WID-1,111,product,pcs,25.50,10,5,2
Gadget,GA-1,222,service,,99.99,,0,0
CSV;

    $this->postJson('/api/v1/products/import', [
        'company_id' => $this->company->id,
        'csv' => $csv,
    ])->assertOk()
        ->assertJsonPath('created', 1)
        ->assertJsonPath('updated', 1)
        ->assertJsonPath('failed', 0);

    $product = Product::where('sku', 'WID-1')->first();

    expect($product->name)->toBe('New Widget')
        ->and((float) $product->sale_price)->toBe(25.5)
        ->and((int) $product->stock_quantity)->toBe(5);
});

it('exports products as csv', function () {
    Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Exportable Widget',
        'sku' => 'EXP-1',
    ]);

    $response = $this->get("/api/v1/products/export?company_id={$this->company->id}");

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $body = $response->streamedContent();

    expect($body)->toContain('name,sku,barcode')
        ->and($body)->toContain('Exportable Widget')
        ->and($body)->toContain('EXP-1');
});

it('exports contacts as csv', function () {
    Contact::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Exportable',
        'last_name' => 'Contact',
    ]);

    $body = $this->get("/api/v1/contacts/export?company_id={$this->company->id}")
        ->assertOk()
        ->streamedContent();

    expect($body)->toContain('id,first_name,last_name')
        ->and($body)->toContain('Exportable');
});

it('exports the invoice register as csv', function () {
    $contact = Contact::factory()->create([
        'company_id' => $this->company->id,
        'first_name' => 'Invoice',
        'last_name' => 'Buyer',
    ]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'invoice_number' => 'INV-EXPORT-1',
        'total' => 100,
        'balance_due' => 100,
    ]);

    $body = $this->get("/api/v1/invoices/export?company_id={$this->company->id}")
        ->assertOk()
        ->streamedContent();

    expect($body)->toContain('invoice_number,type,status')
        ->and($body)->toContain('INV-EXPORT-1')
        ->and($body)->toContain('Invoice Buyer');
});
