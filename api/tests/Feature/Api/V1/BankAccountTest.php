<?php

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
});

it('creates a bank account', function () {
    $this->postJson('/api/v1/bank-accounts', [
        'company_id' => $this->company->id,
        'name' => 'Main Checking',
        'bank_name' => 'Test Bank',
        'account_type' => 'checking',
        'opening_balance' => 1000.50,
        'is_active' => true,
    ])->assertCreated()->assertJsonPath('data.name', 'Main Checking');
});

it('lists bank accounts', function () {
    BankAccount::factory()->count(2)->create(['company_id' => $this->company->id]);

    $this->getJson('/api/v1/bank-accounts?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('updates a bank account', function () {
    $account = BankAccount::factory()->create(['company_id' => $this->company->id]);

    $this->putJson("/api/v1/bank-accounts/{$account->id}", ['opening_balance' => 42])
        ->assertOk()
        ->assertJsonPath('data.opening_balance', 42);
});

it('deletes a bank account', function () {
    $account = BankAccount::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/bank-accounts/{$account->id}")->assertNoContent();
});
