<?php

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\CurrencyRate;
use App\Models\Reconciliation;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
    $this->company = Company::factory()->create();
});

it('soft deletes a warehouse and hides it from the list', function () {
    $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/warehouses/{$warehouse->id}")->assertNoContent();

    $this->assertSoftDeleted($warehouse);

    $this->getJson('/api/v1/warehouses')
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->getJson("/api/v1/warehouses/{$warehouse->id}")->assertNotFound();
});

it('restores a soft deleted warehouse', function () {
    $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);
    $warehouse->delete();

    $response = $this->postJson("/api/v1/warehouses/{$warehouse->id}/restore");

    $response->assertOk()
        ->assertJsonPath('data.id', $warehouse->id);

    expect(Warehouse::withTrashed()->find($warehouse->id)->trashed())->toBeFalse();

    $this->getJson("/api/v1/warehouses/{$warehouse->id}")->assertOk();
});

it('rejects restoring a record that is not trashed', function () {
    $warehouse = Warehouse::factory()->create(['company_id' => $this->company->id]);

    $this->postJson("/api/v1/warehouses/{$warehouse->id}/restore")
        ->assertStatus(422)
        ->assertJsonPath('message', 'This record is not trashed.');
});

it('returns not found when restoring a missing record', function () {
    $this->postJson('/api/v1/warehouses/424242/restore')->assertNotFound();
});

it('soft deletes and restores a bank transaction', function () {
    $bank = BankAccount::factory()->create(['company_id' => $this->company->id]);
    $transaction = BankTransaction::factory()->create([
        'company_id' => $this->company->id,
        'bank_account_id' => $bank->id,
        'amount' => 250,
    ]);

    $this->deleteJson("/api/v1/bank-transactions/{$transaction->id}")->assertNoContent();
    $this->assertSoftDeleted($transaction);

    $this->postJson("/api/v1/bank-transactions/{$transaction->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.id', $transaction->id);

    expect(BankTransaction::find($transaction->id))->not->toBeNull();
});

it('soft deletes and restores a currency rate', function () {
    $rate = CurrencyRate::create([
        'company_id' => $this->company->id,
        'base_currency' => 'USD',
        'currency' => 'EUR',
        'rate' => 0.92,
    ]);

    $this->deleteJson("/api/v1/currency-rates/{$rate->id}")->assertNoContent();
    $this->assertSoftDeleted($rate);

    $this->postJson("/api/v1/currency-rates/{$rate->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.id', $rate->id);

    expect(CurrencyRate::find($rate->id))->not->toBeNull();
});

it('soft deletes and restores a reconciliation', function () {
    $bank = BankAccount::factory()->create(['company_id' => $this->company->id]);
    $reconciliation = Reconciliation::factory()->create([
        'company_id' => $this->company->id,
        'bank_account_id' => $bank->id,
    ]);

    $this->deleteJson("/api/v1/reconciliations/{$reconciliation->id}")->assertNoContent();
    $this->assertSoftDeleted($reconciliation);

    $this->postJson("/api/v1/reconciliations/{$reconciliation->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.id', $reconciliation->id);

    expect(Reconciliation::find($reconciliation->id))->not->toBeNull();
});
