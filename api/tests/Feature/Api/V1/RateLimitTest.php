<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

it('throttles the api per user once the limit is reached', function () {
    config(['api.rate_limit' => 2]);

    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    Passport::actingAs($user);

    $this->getJson('/api/v1/user')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '2');

    $this->getJson('/api/v1/user')->assertOk();

    $this->getJson('/api/v1/user')
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('buckets the throttle per authenticated user', function () {
    config(['api.rate_limit' => 1]);

    $company = Company::factory()->create();

    Passport::actingAs(User::factory()->create(['company_id' => $company->id]));
    $this->getJson('/api/v1/user')->assertOk();
    $this->getJson('/api/v1/user')->assertStatus(429);

    Passport::actingAs(User::factory()->create(['company_id' => $company->id]));
    $this->getJson('/api/v1/user')->assertOk();
});
