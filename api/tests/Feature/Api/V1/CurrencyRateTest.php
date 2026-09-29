<?php

use App\Models\Company;
use App\Models\CurrencyRate;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);

    $this->company = Company::factory()->create();
});

it('stores and lists currency rates', function () {
    $this->postJson('/api/v1/currency-rates', [
        'company_id' => $this->company->id,
        'base_currency' => 'USD',
        'currency' => 'EUR',
        'rate' => 0.92,
    ])->assertCreated()
        ->assertJsonPath('data.currency', 'EUR')
        ->assertJsonPath('data.rate', 0.92);

    $this->getJson('/api/v1/currency-rates?company_id='.$this->company->id)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('upserts a rate for the same company and currency pair', function () {
    $this->postJson('/api/v1/currency-rates', [
        'company_id' => $this->company->id,
        'base_currency' => 'USD',
        'currency' => 'GBP',
        'rate' => 1.30,
    ])->assertCreated();

    $this->postJson('/api/v1/currency-rates', [
        'company_id' => $this->company->id,
        'base_currency' => 'USD',
        'currency' => 'GBP',
        'rate' => 1.35,
    ])->assertCreated();

    expect(CurrencyRate::where('company_id', $this->company->id)->count())->toBe(1)
        ->and((float) CurrencyRate::first()->rate)->toBe(1.35);
});

it('rejects a rate for the base currency itself or zero', function () {
    $this->postJson('/api/v1/currency-rates', [
        'company_id' => $this->company->id,
        'base_currency' => 'USD',
        'currency' => 'USD',
        'rate' => 1,
    ])->assertUnprocessable();

    $this->postJson('/api/v1/currency-rates', [
        'company_id' => $this->company->id,
        'base_currency' => 'USD',
        'currency' => 'EUR',
        'rate' => 0,
    ])->assertUnprocessable();
});

it('converts between currencies using company rates', function () {
    CurrencyRate::create([
        'company_id' => $this->company->id,
        'base_currency' => 'USD',
        'currency' => 'EUR',
        'rate' => 0.90,
    ]);

    $service = app(CurrencyService::class);

    expect($service->convert(100, 'EUR', 'USD', $this->company->id))->toBe(90.0);
    expect($service->convert(90, 'USD', 'EUR', $this->company->id))->toBe(100.0);
    expect($service->convert(100, 'USD', 'USD', $this->company->id))->toBe(100.0);
});

it('falls back to a 1:1 rate when no rate is configured', function () {
    $service = app(CurrencyService::class);

    expect($service->convert(250, 'JPY', 'USD', $this->company->id))->toBe(250.0);
});

it('exposes fx totals on invoices and sales orders', function () {
    CurrencyRate::create([
        'company_id' => $this->company->id,
        'base_currency' => 'USD',
        'currency' => 'EUR',
        'rate' => 0.90,
    ]);

    $invoice = Invoice::factory()->create([
        'company_id' => $this->company->id,
        'currency' => 'EUR',
        'total' => 100,
        'balance_due' => 100,
    ]);

    $this->getJson("/api/v1/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.fx.base_currency', 'USD')
        ->assertJsonPath('data.fx.rate', 0.9)
        ->assertJsonPath('data.fx.base_total', 90);
});
