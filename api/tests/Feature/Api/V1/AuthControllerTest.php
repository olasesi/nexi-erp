<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Token;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->passwordClientSecret = 'test-secret';

    $client = Client::factory()->asPasswordClient()->create([
        'redirect' => 'http://localhost',
        'secret' => $this->passwordClientSecret,
    ]);

    config([
        'services.passport.password_client_id' => $client->id,
        'services.passport.password_client_secret' => $this->passwordClientSecret,
    ]);

    $this->user = User::factory()->create([
        'email' => 'test@nexi-corp.com',
        'password' => 'password',
    ]);
});

it('logs a user in and returns an OAuth2 access token', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'test@nexi-corp.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token_type',
            'expires_in',
            'access_token',
            'refresh_token',
            'user' => ['id', 'name', 'email'],
        ])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.email', 'test@nexi-corp.com');
});

it('rejects invalid credentials on login', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'test@nexi-corp.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable();
});

it('registers a user and returns an OAuth2 access token', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'New User',
        'email' => 'new@nexi-corp.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token_type', 'expires_in', 'access_token', 'refresh_token', 'user']);

    expect(User::where('email', 'new@nexi-corp.com')->exists())->toBeTrue();
});

it('fetches the authenticated user', function () {
    $login = $this->postJson('/api/v1/login', [
        'email' => 'test@nexi-corp.com',
        'password' => 'password',
    ])->assertOk();

    $token = $login->json('access_token');

    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('email', 'test@nexi-corp.com');
});

it('logout revokes the access token', function () {
    $login = $this->postJson('/api/v1/login', [
        'email' => 'test@nexi-corp.com',
        'password' => 'password',
    ])->assertOk();

    $token = $login->json('access_token');

    $this->withToken($token)
        ->postJson('/api/v1/logout')
        ->assertOk()->assertJsonPath('message', 'Logged out successfully');

    $token = Token::where('user_id', $this->user->id)->latest('id')->first();

    expect($token)->not->toBeNull();
    expect((bool) $token->revoked)->toBeTrue();
});

it('requires a valid token to access protected routes', function () {
    $this->getJson('/api/v1/user')->assertUnauthorized();
});
