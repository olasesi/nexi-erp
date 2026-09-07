<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\GeneralLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
});

it('returns dashboard KPIs', function () {
    $contact = Contact::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'customer',
    ]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'sent',
        'subtotal' => 250,
        'tax_amount' => 0,
        'total' => 250,
        'balance_due' => 250,
    ]);

    Product::factory()->create([
        'company_id' => $this->company->id,
        'name' => 'Low stock item',
        'type' => 'product',
        'stock_quantity' => 2,
        'min_stock_level' => 5,
    ]);

    $response = $this->getJson('/api/v1/dashboard?company_id='.$this->company->id)
        ->assertOk();

    expect($response->json('total_invoiced'))->toBe(250)
        ->and($response->json('outstanding_receivables'))->toBe(250)
        ->and($response->json('low_stock_products'))->toBe(1);
});

it('returns WorkDo-style financial KPIs', function () {
    Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'customer']);
    Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'both']);
    Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'supplier']);
    Contact::factory()->create(['company_id' => $this->company->id, 'type' => 'lead']);

    Payment::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'receipt',
        'status' => 'completed',
        'amount' => 300,
    ]);

    Payment::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'payment',
        'status' => 'completed',
        'amount' => 75,
    ]);

    $gl = app(GeneralLedgerService::class);
    $gl->post($this->company->id, now()->toDateString(), 'Consulting', [
        ['account_code' => '1010', 'debit' => 500],
        ['account_code' => '4010', 'credit' => 500],
    ], $this->company);
    $gl->post($this->company->id, now()->toDateString(), 'Office rent', [
        ['account_code' => '5200', 'debit' => 120],
        ['account_code' => '1010', 'credit' => 120],
    ], $this->company);

    $response = $this->getJson('/api/v1/dashboard?company_id='.$this->company->id)
        ->assertOk();

    expect($response->json('total_clients'))->toBe(2)
        ->and($response->json('total_vendors'))->toBe(2)
        ->and($response->json('total_customer_payment'))->toBe(300)
        ->and($response->json('total_vendor_payment'))->toBe(75)
        ->and($response->json('month_revenue'))->toBe(500)
        ->and($response->json('month_expense'))->toBe(120)
        ->and($response->json('month_net_profit'))->toBe(380)
        ->and($response->json('monthly_customer_payments'))->toHaveCount(6)
        ->and($response->json('recent_revenues.0.amount'))->toBe(500)
        ->and($response->json('recent_expenses.0.amount'))->toBe(120);
});
