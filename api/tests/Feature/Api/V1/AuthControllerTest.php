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

it('no longer exposes public staff self-registration', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'New User',
        'email' => 'new@nexi-corp.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertNotFound();

    expect(User::where('email', 'new@nexi-corp.com')->exists())->toBeFalse();
});

it('fetches the authenticated user with roles and permissions', function () {
    $login = $this->postJson('/api/v1/login', [
        'email' => 'test@nexi-corp.com',
        'password' => 'password',
    ])->assertOk();

    $token = $login->json('access_token');

    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('email', 'test@nexi-corp.com')
        ->assertJsonStructure(['roles', 'permissions']);
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

it('updates the authenticated profile', function () {
    $login = $this->postJson('/api/v1/login', [
        'email' => 'test@nexi-corp.com',
        'password' => 'password',
    ])->assertOk();

    $response = $this->withToken($login->json('access_token'))
        ->putJson('/api/v1/user', [
            'name' => 'Renamed Admin',
            'username' => 'renamed',
            'phone' => '+1 555 0100',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Renamed Admin')
        ->assertJsonPath('username', 'renamed')
        ->assertJsonPath('phone', '+1 555 0100')
        ->assertJsonStructure(['roles', 'permissions', 'company']);

    expect($this->user->refresh())
        ->name->toBe('Renamed Admin')
        ->username->toBe('renamed')
        ->phone->toBe('+1 555 0100');

    expect($response->json('email'))->toBe('test@nexi-corp.com');
});

it('allows clearing the optional profile fields', function () {
    $this->user->forceFill(['username' => 'taken', 'phone' => '+1 555 0199'])->save();

    $login = $this->postJson('/api/v1/login', [
        'email' => 'test@nexi-corp.com',
        'password' => 'password',
    ])->assertOk();

    $this->withToken($login->json('access_token'))
        ->putJson('/api/v1/user', ['username' => null, 'phone' => null])
        ->assertOk()
        ->assertJsonPath('username', null)
        ->assertJsonPath('phone', null);
});

it('does not let a profile update change account level fields', function () {
    $login = $this->postJson('/api/v1/login', [
        'email' => 'test@nexi-corp.com',
        'password' => 'password',
    ])->assertOk();

    $this->withToken($login->json('access_token'))
        ->putJson('/api/v1/user', [
            'email' => 'hijack@nexi-corp.com',
            'is_active' => false,
            'roles' => ['admin'],
            'name' => 'Renamed Admin',
        ])
        ->assertOk()
        ->assertJsonPath('email', 'test@nexi-corp.com')
        ->assertJsonPath('is_active', true)
        ->assertJsonPath('roles', []);

    expect($this->user->refresh())
        ->email->toBe('test@nexi-corp.com')
        ->name->toBe('Renamed Admin');

    expect(User::where('email', 'hijack@nexi-corp.com')->exists())->toBeFalse();
});

it('rejects an invalid or duplicate profile payload', function () {
    $other = User::factory()->create(['username' => 'already-taken']);

    $login = $this->postJson('/api/v1/login', [
        'email' => 'test@nexi-corp.com',
        'password' => 'password',
    ])->assertOk();

    $token = $login->json('access_token');

    $this->withToken($token)
        ->putJson('/api/v1/user', ['username' => $other->username])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('username');

    $this->withToken($token)
        ->putJson('/api/v1/user', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('requires a valid token to update the profile', function () {
    $this->putJson('/api/v1/user', ['name' => 'No token'])->assertUnauthorized();
});
