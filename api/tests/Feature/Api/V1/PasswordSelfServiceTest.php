<?php

use App\Models\User;
use App\Notifications\PasswordResetToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'selfservice@nexi-corp.com',
        'password' => 'old-password',
    ]);
});

it('changes the password after verifying the current one', function () {
    Passport::actingAs($this->user);

    $this->postJson('/api/v1/password/change', [
        'current_password' => 'old-password',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertOk()->assertJsonPath('message', 'Password changed successfully.');

    expect(Hash::check('new-password123', $this->user->fresh()->password))->toBeTrue();
});

it('rejects a password change when the current password is wrong', function () {
    Passport::actingAs($this->user);

    $this->postJson('/api/v1/password/change', [
        'current_password' => 'not-the-password',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');
});

it('requires authentication to change the password', function () {
    $this->postJson('/api/v1/password/change', [
        'current_password' => 'old-password',
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])->assertUnauthorized();
});

it('emails a reset token for a known account', function () {
    Notification::fake();

    $this->postJson('/api/v1/password/forgot', ['email' => 'selfservice@nexi-corp.com'])
        ->assertOk()->assertJsonPath('message', 'If that email exists, a reset token has been sent.');

    Notification::assertSentTo($this->user, PasswordResetToken::class);
});

it('never reveals whether an email is registered', function () {
    Notification::fake();

    $this->postJson('/api/v1/password/forgot', ['email' => 'does-not-exist@nexi-corp.com'])
        ->assertOk()->assertJsonPath('message', 'If that email exists, a reset token has been sent.');

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    $token = Password::broker()->createToken($this->user);

    $this->postJson('/api/v1/password/reset', [
        'token' => $token,
        'email' => 'selfservice@nexi-corp.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertOk()->assertJsonPath('message', 'Password reset successfully.');

    expect(Hash::check('brand-new-password', $this->user->fresh()->password))->toBeTrue();
});

it('rejects an invalid reset token', function () {
    $this->postJson('/api/v1/password/reset', [
        'token' => 'not-a-real-token',
        'email' => 'selfservice@nexi-corp.com',
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});
