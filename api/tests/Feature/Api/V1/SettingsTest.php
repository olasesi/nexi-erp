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
        ->and($body['meta']['available_languages'])->toBe([
            'en' => 'English',
            'es' => 'Español',
            'zh' => '中文',
            'hi' => 'हिन्दी',
            'ar' => 'العربية',
            'fr' => 'Français',
            'pt' => 'Português',
            'ru' => 'Русский',
            'id' => 'Bahasa Indonesia',
            'de' => 'Deutsch',
            'ja' => '日本語',
            'tr' => 'Türkçe',
            'vi' => 'Tiếng Việt',
            'ko' => '한국어',
            'it' => 'Italiano',
        ])
        ->and(count($body['meta']['available_languages']))->toBe(15)
        ->and($settings->json('data.system.defaultLanguage'))->toBe('en')
        ->and($body['meta']['themes'])->toBe(['light', 'twilight', 'dark'])
        ->and(count($body['meta']['theme_colors']))->toBe(13);
});

it('returns the dashboard layout group with its default and the preset registry', function () {
    Passport::actingAs(User::factory()->create());

    $settings = $this->getJson('/api/v1/settings')->assertOk();

    $settings->assertJsonPath('data.dashboard.layout', 'minimal')
        ->assertJsonPath('data.dashboard.density', 'comfortable');

    $body = $settings->json();
    expect($body['meta']['dashboard_layouts'])->toHaveKeys(['minimal', 'maximal', 'executive', 'analytics', 'operations'])
        ->and($body['meta']['dashboard_widgets'])->toBeArray()
        ->and(count($body['meta']['dashboard_layouts']))->toBe(5);
});

it('defaults the dashboard layout and density and validates against the available presets', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/settings/dashboard')
        ->assertOk()
        ->assertJsonPath('data.layout', 'minimal')
        ->assertJsonPath('data.density', 'comfortable');

    $this->putJson('/api/v1/settings/dashboard', [
        'layout' => 'executive',
        'density' => 'compact',
    ])->assertOk()
        ->assertJsonPath('data.layout', 'executive')
        ->assertJsonPath('data.density', 'compact');

    $this->putJson('/api/v1/settings/dashboard', ['layout' => 'neon'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['layout']);

    $this->putJson('/api/v1/settings/dashboard', ['density' => 'spacious'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['density']);
});

it('defaults to English, restricts the language to the available options, and persists a change', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/settings/system')
        ->assertOk()
        ->assertJsonPath('data.defaultLanguage', 'en');

    $this->putJson('/api/v1/settings/system', ['defaultLanguage' => 'fr'])
        ->assertOk()
        ->assertJsonPath('data.defaultLanguage', 'fr');

    $this->putJson('/api/v1/settings/system', ['defaultLanguage' => 'xx'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['defaultLanguage']);
});

it('defaults to light theme and validates theme mode and theme color options', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/settings/brand')
        ->assertOk()
        ->assertJsonPath('data.themeMode', 'light')
        ->assertJsonPath('data.themeColor', 'green');

    $this->putJson('/api/v1/settings/brand', [
        'themeMode' => 'twilight',
        'themeColor' => 'teal',
    ])->assertOk()
        ->assertJsonPath('data.themeMode', 'twilight')
        ->assertJsonPath('data.themeColor', 'teal');

    $this->putJson('/api/v1/settings/brand', ['themeMode' => 'neon'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['themeMode']);

    $this->putJson('/api/v1/settings/brand', ['themeColor' => 'mauve'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['themeColor']);
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
