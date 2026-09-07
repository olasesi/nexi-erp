<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

it('lists supported currencies with the default first', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/currencies')
        ->assertOk()
        ->assertJsonCount(155, 'data')
        ->assertJsonPath('data.0.code', 'USD')
        ->assertJsonPath('data.0.is_default', true)
        ->assertJsonPath('meta.total', 155);
});

it('filters currencies by code or name', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/currencies?search=usd')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'USD')
        ->assertJsonPath('data.0.is_default', true);

    $this->getJson('/api/v1/currencies?search=South Korean Won')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'KRW');
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/currencies')->assertUnauthorized();
});
