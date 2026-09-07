<?php

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

it('returns all settings groups with brand defaults and UI options', function () {
    Passport::actingAs(User::factory()->create());

    $settings = $this->getJson('/api/v1/settings')->assertOk();
    $settings->assertJsonPath('data.brand.titleText', 'Nexi ERP')
        ->assertJsonPath('data.currency.defaultCurrency', 'USD')
        ->assertJsonPath('data.storage.maxUploadSize', '2048')
        ->assertJsonPath('data.email.mailDriver', 'log');

    $body = $settings->json();
    expect(count($body['meta']['currencies']))->toBe(155)
        ->and($body['meta']['default_currency'])->toBe('USD')
        ->and(count($body['meta']['available_languages']))->toBeGreaterThan(10);
});

it('updates a settings group and persists values scoped to company', function () {
    $company = Company::factory()->create();
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/settings/brand?company_id='.$company->id, [
        'titleText' => 'Acme Corp',
        'themeMode' => 'dark',
        'themeColor' => 'red',
    ])->assertOk()
        ->assertJsonPath('data.titleText', 'Acme Corp')
        ->assertJsonPath('data.themeMode', 'dark');

    $this->assertDatabaseHas('settings', [
        'company_id' => $company->id,
        'group' => 'brand',
        'key' => 'titleText',
        'value' => 'Acme Corp',
    ]);

    $this->getJson('/api/v1/settings/brand?company_id='.$company->id)
        ->assertOk()
        ->assertJsonPath('data.titleText', 'Acme Corp');

    $this->getJson('/api/v1/settings/brand')
        ->assertOk()
        ->assertJsonPath('data.titleText', 'Nexi ERP');
});

it('stores the mail password encrypted at rest and returns it decrypted', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/settings/email', [
        'mailDriver' => 'smtp',
        'mailPassword' => 's3cret!',
    ])->assertOk();

    $row = Setting::where('group', 'email')->where('key', 'mailPassword')->firstOrFail();
    expect($row->value)->not->toBe('s3cret!');

    $this->getJson('/api/v1/settings/email')
        ->assertOk()
        ->assertJsonPath('data.mailPassword', 's3cret!');
});

it('rejects unknown keys and invalid option values for a group', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/settings/brand', [
        'titleText' => 'Acme Corp',
        'notARealKey' => 'nope',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['notARealKey']);

    $this->putJson('/api/v1/settings/brand', [
        'themeMode' => 'neon',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['themeMode']);

    $this->putJson('/api/v1/settings/unknown', [
        'foo' => 'bar',
    ])->assertNotFound();

    $this->getJson('/api/v1/settings/unknown')->assertNotFound();
});

it('clears the application cache', function () {
    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/v1/settings/cache/clear')
        ->assertOk()
        ->assertJsonPath('message', 'Cache cleared successfully.')
        ->assertJsonPath('cache_size', '0.00');
});

it('sends a test email using the configured transport', function () {
    Passport::actingAs(User::factory()->create());

    $this->postJson('/api/v1/settings/email/test', [
        'email' => 'ops@acme.test',
    ])->assertOk()
        ->assertJsonPath('message', 'Test email sent successfully.');
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/settings')->assertUnauthorized();
    $this->putJson('/api/v1/settings/brand')->assertUnauthorized();
    $this->postJson('/api/v1/settings/cache/clear')->assertUnauthorized();
});
