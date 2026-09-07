<?php

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Reconciliation;
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

it('opens a reconciliation and completes it', function () {
    $txn1 = BankTransaction::factory()->create([
        'company_id' => $this->company->id,
        'bank_account_id' => $this->bank->id,
        'amount' => 100,
    ]);
    $txn2 = BankTransaction::factory()->create([
        'company_id' => $this->company->id,
        'bank_account_id' => $this->bank->id,
        'amount' => -50,
    ]);

    $response = $this->postJson('/api/v1/reconciliations', [
        'company_id' => $this->company->id,
        'bank_account_id' => $this->bank->id,
        'period_start' => '2026-09-01',
        'period_end' => '2026-09-30',
        'opening_balance' => 1000,
        'closing_balance' => 1050,
        'transaction_ids' => [$txn1->id, $txn2->id],
    ])->assertCreated()->assertJsonPath('data.status', 'open');

    $reconciliation = Reconciliation::first();

    expect($reconciliation->items()->count())->toBe(2);

    $this->postJson("/api/v1/reconciliations/{$reconciliation->id}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');

    expect($txn1->fresh()->reconciliation_status)->toBe('reconciled')
        ->and($txn2->fresh()->reconciliation_status)->toBe('reconciled');
});

it('does not complete a reconciliation twice', function () {
    $reconciliation = Reconciliation::factory()->create([
        'company_id' => $this->company->id,
        'bank_account_id' => $this->bank->id,
        'status' => 'completed',
    ]);

    $this->postJson("/api/v1/reconciliations/{$reconciliation->id}/complete")->assertStatus(422);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/reconciliations')->assertUnauthorized();
});
