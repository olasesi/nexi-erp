<?php

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists expenses', function () {
    Expense::factory(3)->create();

    $response = $this->getJson('/api/v1/expenses');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows an expense', function () {
    $expense = Expense::factory()->create();

    $response = $this->getJson("/api/v1/expenses/{$expense->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $expense->id);
});

it('creates an expense', function () {
    $company = Company::factory()->create();
    $category = ExpenseCategory::factory()->create(['company_id' => $company->id]);

    $response = $this->postJson('/api/v1/expenses', [
        'company_id' => $company->id,
        'expense_category_id' => $category->id,
        'reference_no' => 'EXP-001',
        'amount' => 150.00,
        'date' => '2025-01-15',
        'payment_method' => 'cash',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.reference_no', 'EXP-001');
});

it('creates an expense with auto-generated reference', function () {
    $company = Company::factory()->create();
    $category = ExpenseCategory::factory()->create(['company_id' => $company->id]);

    $response = $this->postJson('/api/v1/expenses', [
        'company_id' => $company->id,
        'expense_category_id' => $category->id,
        'amount' => 75.00,
        'date' => '2025-01-15',
        'payment_method' => 'bank_transfer',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['reference_no']]);
});

it('updates an expense', function () {
    $expense = Expense::factory()->create();

    $response = $this->putJson("/api/v1/expenses/{$expense->id}", [
        'amount' => 250.00,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.amount', 250);
});

it('deletes an expense', function () {
    $expense = Expense::factory()->create();

    $response = $this->deleteJson("/api/v1/expenses/{$expense->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($expense);
});

it('filters expenses by category', function () {
    $company = Company::factory()->create();
    $categoryA = ExpenseCategory::factory()->create(['company_id' => $company->id]);
    $categoryB = ExpenseCategory::factory()->create(['company_id' => $company->id]);

    Expense::factory()->create(['company_id' => $company->id, 'expense_category_id' => $categoryA->id]);
    Expense::factory()->create(['company_id' => $company->id, 'expense_category_id' => $categoryB->id]);

    $response = $this->getJson("/api/v1/expenses?expense_category_id={$categoryA->id}");

    $response->assertOk();
    expect(collect($response->json('data'))->pluck('expense_category_id')->unique()->values()->first())->toBe($categoryA->id);
});

it('filters expenses by date range', function () {
    Expense::factory()->create(['date' => '2025-01-10']);
    Expense::factory()->create(['date' => '2025-06-15']);

    $response = $this->getJson('/api/v1/expenses?from_date=2025-06-01&to_date=2025-06-30');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/expenses')->assertUnauthorized();
});
