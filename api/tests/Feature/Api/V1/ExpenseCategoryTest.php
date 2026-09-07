<?php

use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists expense categories', function () {
    ExpenseCategory::factory(3)->create();

    $response = $this->getJson('/api/v1/expense-categories');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows an expense category', function () {
    $expenseCategory = ExpenseCategory::factory()->create();

    $response = $this->getJson("/api/v1/expense-categories/{$expenseCategory->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $expenseCategory->id);
});

it('creates an expense category', function () {
    $company = Company::factory()->create();

    $response = $this->postJson('/api/v1/expense-categories', [
        'company_id' => $company->id,
        'name' => 'Office Supplies',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Office Supplies');
});

it('updates an expense category', function () {
    $expenseCategory = ExpenseCategory::factory()->create();

    $response = $this->putJson("/api/v1/expense-categories/{$expenseCategory->id}", [
        'name' => 'Updated Category',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Category');
});

it('deletes an expense category', function () {
    $expenseCategory = ExpenseCategory::factory()->create();

    $response = $this->deleteJson("/api/v1/expense-categories/{$expenseCategory->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($expenseCategory);
});

it('searches expense categories by name', function () {
    ExpenseCategory::factory()->create(['name' => 'Office Supplies']);
    ExpenseCategory::factory()->create(['name' => 'Travel Expenses']);

    $response = $this->getJson('/api/v1/expense-categories?search=Office');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/expense-categories')->assertUnauthorized();
});
