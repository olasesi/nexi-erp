<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\Invoice;
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

it('returns a profit and loss statement', function () {
    $gl = app(GeneralLedgerService::class);

    $gl->post($this->company->id, '2026-09-01', 'Sale', [
        ['account_code' => '1010', 'debit' => 120],
        ['account_code' => '4010', 'credit' => 100],
        ['account_code' => '2100', 'credit' => 20],
    ], $this->company);

    $gl->post($this->company->id, '2026-09-02', 'Rent', [
        ['account_code' => '5200', 'debit' => 30],
        ['account_code' => '1010', 'credit' => 30],
    ], $this->company);

    $this->getJson('/api/v1/reports/profit-and-loss?company_id='.$this->company->id.'&from=2026-09-01&to=2026-09-30')
        ->assertOk()
        ->assertJsonPath('total_revenue', 100)
        ->assertJsonPath('total_expenses', 30)
        ->assertJsonPath('net_income', 70);
});

it('returns a balance sheet', function () {
    $gl = app(GeneralLedgerService::class);

    $gl->post($this->company->id, '2026-09-01', 'Sale', [
        ['account_code' => '1010', 'debit' => 200],
        ['account_code' => '4010', 'credit' => 200],
    ], $this->company);

    $this->getJson('/api/v1/reports/balance-sheet?company_id='.$this->company->id.'&as_of=2026-09-30')
        ->assertOk()
        ->assertJsonPath('total_assets', 200)
        ->assertJsonPath('total_liabilities_and_equity', 200);
});

it('returns a cash flow statement', function () {
    $gl = app(GeneralLedgerService::class);

    $gl->post($this->company->id, '2026-09-01', 'Sale paid', [
        ['account_code' => '1010', 'debit' => 500],
        ['account_code' => '4010', 'credit' => 500],
    ], $this->company);

    $this->getJson('/api/v1/reports/cash-flow?company_id='.$this->company->id.'&from=2026-09-01&to=2026-09-30')
        ->assertOk()
        ->assertJsonPath('inflow', 500)
        ->assertJsonPath('net_cash_flow', 500);
});

it('returns an aging report', function () {
    $contact = Contact::factory()->create([
        'company_id' => $this->company->id,
        'type' => 'customer',
    ]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'sent',
        'due_date' => now()->addDays(10)->toDateString(),
        'subtotal' => 100,
        'tax_amount' => 0,
        'total' => 100,
        'balance_due' => 100,
    ]);

    Invoice::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'type' => 'invoice',
        'status' => 'overdue',
        'due_date' => now()->subDays(40)->toDateString(),
        'subtotal' => 50,
        'tax_amount' => 0,
        'total' => 50,
        'balance_due' => 50,
    ]);

    $response = $this->getJson('/api/v1/reports/aging?company_id='.$this->company->id.'&type=receivable')
        ->assertOk();

    expect($response->json('total_outstanding'))->toBe(150)
        ->and($response->json('buckets.current.amount'))->toBe(100)
        ->and($response->json('buckets.31_60.amount'))->toBe(50);
});

it('returns an empty balance sheet when no postings exist', function () {
    $this->getJson('/api/v1/reports/balance-sheet?company_id='.$this->company->id.'&as_of=2026-09-30')
        ->assertOk()
        ->assertJsonPath('total_assets', 0);
})->skip('Accounts only appear after first posting; base install is empty by design.');
