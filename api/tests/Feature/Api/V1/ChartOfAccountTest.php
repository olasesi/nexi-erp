<?php

use App\Models\ChartOfAccount;
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

it('lists chart of accounts', function () {
    ChartOfAccount::factory()->count(3)->create(['company_id' => $this->company->id]);

    $response = $this->getJson('/api/v1/chart-of-accounts?company_id='.$this->company->id);

    $response->assertOk()->assertJsonStructure(['data', 'meta', 'links']);
});

it('creates a chart of account', function () {
    $response = $this->postJson('/api/v1/chart-of-accounts', [
        'company_id' => $this->company->id,
        'code' => '6000',
        'name' => 'Consulting Revenue',
        'type' => 'revenue',
        'normal_balance' => 'credit',
    ])->assertCreated();

    $response->assertJsonPath('data.code', '6000');
});

it('validates a chart of account balance type', function () {
    $this->postJson('/api/v1/chart-of-accounts', [
        'company_id' => $this->company->id,
        'code' => '6001',
        'name' => 'Bad',
        'type' => 'revenue',
        'normal_balance' => 'diagonal',
    ])->assertStatus(422);
});

it('updates a chart of account', function () {
    $account = ChartOfAccount::factory()->create(['company_id' => $this->company->id]);

    $this->putJson("/api/v1/chart-of-accounts/{$account->id}", ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed');
});

it('deletes a chart of account', function () {
    $account = ChartOfAccount::factory()->create(['company_id' => $this->company->id]);

    $this->deleteJson("/api/v1/chart-of-accounts/{$account->id}")->assertNoContent();
});
