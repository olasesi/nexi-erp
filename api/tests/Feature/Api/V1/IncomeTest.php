<?php

use App\Models\Company;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists incomes', function () {
    Income::factory(3)->create();

    $response = $this->getJson('/api/v1/incomes');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows an income', function () {
    $income = Income::factory()->create();

    $response = $this->getJson("/api/v1/incomes/{$income->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $income->id);
});

it('creates an income', function () {
    $company = Company::factory()->create();
    $category = IncomeCategory::factory()->create(['company_id' => $company->id]);

    $response = $this->postJson('/api/v1/incomes', [
        'company_id' => $company->id,
        'income_category_id' => $category->id,
        'reference_no' => 'INC-001',
        'amount' => 500.00,
        'date' => '2025-01-15',
        'payment_method' => 'bank_transfer',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.reference_no', 'INC-001');
});

it('creates an income with auto-generated reference', function () {
    $company = Company::factory()->create();
    $category = IncomeCategory::factory()->create(['company_id' => $company->id]);

    $response = $this->postJson('/api/v1/incomes', [
        'company_id' => $company->id,
        'income_category_id' => $category->id,
        'amount' => 200.00,
        'date' => '2025-01-15',
        'payment_method' => 'cash',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['reference_no']]);
});

it('updates an income', function () {
    $income = Income::factory()->create();

    $response = $this->putJson("/api/v1/incomes/{$income->id}", [
        'amount' => 750.00,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.amount', 750);
});

it('deletes an income', function () {
    $income = Income::factory()->create();

    $response = $this->deleteJson("/api/v1/incomes/{$income->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($income);
});

it('filters incomes by category', function () {
    $company = Company::factory()->create();
    $categoryA = IncomeCategory::factory()->create(['company_id' => $company->id]);
    $categoryB = IncomeCategory::factory()->create(['company_id' => $company->id]);

    Income::factory()->create(['company_id' => $company->id, 'income_category_id' => $categoryA->id]);
    Income::factory()->create(['company_id' => $company->id, 'income_category_id' => $categoryB->id]);

    $response = $this->getJson("/api/v1/incomes?income_category_id={$categoryA->id}");

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('income_category_id')->unique()->values()->first())->toBe($categoryA->id);
});

it('filters incomes by date range', function () {
    Income::factory()->create(['date' => '2025-01-10']);
    Income::factory()->create(['date' => '2025-06-15']);

    $response = $this->getJson('/api/v1/incomes?from_date=2025-06-01&to_date=2025-06-30');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/incomes')->assertUnauthorized();
});
