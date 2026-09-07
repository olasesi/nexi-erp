<?php

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
    $this->bank = BankAccount::factory()->create(['company_id' => $this->company->id]);
});

it('imports bank transactions in bulk', function () {
    $response = $this->postJson('/api/v1/bank-transactions/import', [
        'company_id' => $this->company->id,
        'bank_account_id' => $this->bank->id,
        'transactions' => [
            ['transaction_date' => '2026-09-01', 'description' => 'Deposit', 'amount' => 500],
            ['transaction_date' => '2026-09-02', 'description' => 'Withdrawal', 'amount' => -120.50],
        ],
    ])->assertCreated();

    expect($response->json('data'))->toHaveCount(2)
        ->and(BankTransaction::count())->toBe(2)
        ->and(BankTransaction::first()->reconciliation_status)->toBe('unreconciled');
});

it('matches a bank transaction to a payment', function () {
    $transaction = BankTransaction::factory()->create([
        'company_id' => $this->company->id,
        'bank_account_id' => $this->bank->id,
        'amount' => 100,
    ]);

    $contact = Contact::factory()->create(['company_id' => $this->company->id]);
    $payment = Payment::factory()->create([
        'company_id' => $this->company->id,
        'contact_id' => $contact->id,
        'amount' => 100,
        'status' => 'completed',
    ]);

    $this->postJson("/api/v1/bank-transactions/{$transaction->id}/match", ['payment_id' => $payment->id])
        ->assertOk()
        ->assertJsonPath('data.reconciliation_status', 'matched')
        ->assertJsonPath('data.matched_payment_id', $payment->id);
});

it('unmatches a bank transaction', function () {
    $transaction = BankTransaction::factory()->create([
        'company_id' => $this->company->id,
        'bank_account_id' => $this->bank->id,
        'reconciliation_status' => 'matched',
    ]);

    $this->postJson("/api/v1/bank-transactions/{$transaction->id}/unmatch")
        ->assertOk()
        ->assertJsonPath('data.reconciliation_status', 'unreconciled')
        ->assertJsonPath('data.matched_payment_id', null);
});

it('lists bank transactions with filters', function () {
    BankTransaction::factory()->count(3)->create([
        'company_id' => $this->company->id,
        'bank_account_id' => $this->bank->id,
        'reconciliation_status' => 'unreconciled',
    ]);

    $this->getJson('/api/v1/bank-transactions?company_id='.$this->company->id.'&reconciliation_status=unreconciled')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});
