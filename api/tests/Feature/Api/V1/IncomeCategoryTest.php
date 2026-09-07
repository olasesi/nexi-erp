<?php

use App\Models\Company;
use App\Models\IncomeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Passport::actingAs($this->user);
});

it('lists income categories', function () {
    IncomeCategory::factory(3)->create();

    $response = $this->getJson('/api/v1/income-categories');

    $response->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('shows an income category', function () {
    $incomeCategory = IncomeCategory::factory()->create();

    $response = $this->getJson("/api/v1/income-categories/{$incomeCategory->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $incomeCategory->id);
});

it('creates an income category', function () {
    $company = Company::factory()->create();

    $response = $this->postJson('/api/v1/income-categories', [
        'company_id' => $company->id,
        'name' => 'Product Sales',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Product Sales');
});

it('updates an income category', function () {
    $incomeCategory = IncomeCategory::factory()->create();

    $response = $this->putJson("/api/v1/income-categories/{$incomeCategory->id}", [
        'name' => 'Updated Category',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Category');
});

it('deletes an income category', function () {
    $incomeCategory = IncomeCategory::factory()->create();

    $response = $this->deleteJson("/api/v1/income-categories/{$incomeCategory->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted($incomeCategory);
});

it('searches income categories by name', function () {
    IncomeCategory::factory()->create(['name' => 'Product Sales']);
    IncomeCategory::factory()->create(['name' => 'Service Income']);

    $response = $this->getJson('/api/v1/income-categories?search=Product');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/income-categories')->assertUnauthorized();
});
