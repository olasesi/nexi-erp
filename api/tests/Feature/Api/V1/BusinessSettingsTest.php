<?php

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

it('returns all business settings groups with typed defaults and meta', function () {
    Passport::actingAs(User::factory()->create());

    $response = $this->getJson('/api/v1/business-settings')
        ->assertOk()
        ->assertJsonPath('data.business.currency', 'USD')
        ->assertJsonPath('data.business.default_profit_percent', '25.00')
        ->assertJsonPath('data.modules.enable_pos', true)
        ->assertJsonPath('data.prefixes.purchase', 'PO')
        ->assertJsonPath('data.pos.shortcut_express_checkout', 'shift+e');

    $body = $response->json();

    expect(count($body['data']))->toBeGreaterThanOrEqual(30)
        ->and(count($body['meta']['groups']))->toBeGreaterThanOrEqual(30)
        ->and($body['data'])->toHaveKey('stripe')
        ->and($body['data'])->toHaveKey('twilio')
        ->and($body['meta']['options']['business']['currency'])->toHaveKey('USD')
        ->and($body['meta']['options']['business']['time_zone'])->toHaveKey('UTC');
});

it('shows a single group with defaults', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/business-settings/prefixes')
        ->assertOk()
        ->assertJsonPath('data.purchase', 'PO')
        ->assertJsonPath('data.stock_transfer', 'ST')
        ->assertJsonPath('data.business_location', 'BL');
});

it('updates a group and returns typed values', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/business-settings/sale', [
        'default_sale_discount' => '15.00',
        'allow_overselling' => '1',
        'enable_sales_order' => true,
        'commission_calculation_type' => 'profit',
    ])->assertOk()
        ->assertJsonPath('message', 'Sale settings updated successfully.')
        ->assertJsonPath('data.default_sale_discount', '15.00')
        ->assertJsonPath('data.allow_overselling', true)
        ->assertJsonPath('data.enable_sales_order', true)
        ->assertJsonPath('data.commission_calculation_type', 'profit');

    $this->assertDatabaseHas('settings', [
        'namespace' => 'business',
        'group' => 'sale',
        'key' => 'default_sale_discount',
        'value' => '15.00',
    ]);
});

it('persists custom label objects and payment method selections', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/business-settings/custom_labels', [
        'product_custom_field_1' => ['label' => 'Serial No', 'field_type' => 'date'],
    ])->assertOk()
        ->assertJsonPath('data.product_custom_field_1.label', 'Serial No');

    $this->putJson('/api/v1/business-settings/payment', [
        'enable_cash_denomination_for_payment_methods' => ['cash', 'cheque'],
    ])->assertOk()
        ->assertJsonPath('data.enable_cash_denomination_for_payment_methods', ['cash', 'cheque']);

    $this->assertDatabaseHas('settings', [
        'namespace' => 'business',
        'group' => 'payment',
        'key' => 'enable_cash_denomination_for_payment_methods',
        'value' => json_encode(['cash', 'cheque']),
    ]);
});

it('encrypts secrets through the API and returns them decrypted', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/business-settings/email', ['mail_password' => 'hunter2'])
        ->assertOk()
        ->assertJsonPath('data.mail_password', 'hunter2');

    $row = Setting::where('namespace', 'business')->where('group', 'email')->where('key', 'mail_password')->firstOrFail();
    expect($row->value)->not->toBe('hunter2');
});

it('scopes updates to the provided company', function () {
    Passport::actingAs(User::factory()->create());
    $company = Company::factory()->create();

    $this->putJson('/api/v1/business-settings/business?company_id='.$company->id, [
        'business_name' => 'Acme Corp',
    ])->assertOk()
        ->assertJsonPath('data.business_name', 'Acme Corp');

    $this->getJson('/api/v1/business-settings/business')
        ->assertOk()
        ->assertJsonPath('data.business_name', null);
});

it('validates values against types and option lists', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/business-settings/business', ['currency' => 'XXX'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency']);

    $this->putJson('/api/v1/business-settings/business', ['transaction_edit_days' => 'abc'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['transaction_edit_days']);

    $this->putJson('/api/v1/business-settings/pos', ['disable_discount' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['disable_discount']);

    $this->putJson('/api/v1/business-settings/sale', ['not_a_setting' => 'x'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['not_a_setting']);
});

it('returns 404 for unknown groups', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/business-settings/nope')->assertNotFound();
    $this->putJson('/api/v1/business-settings/nope', [])->assertNotFound();
});

it('updates a generated integration group and encrypts its credentials', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/business-settings/stripe', [
        'enabled' => '1',
        'publishable_key' => 'pk_test_123',
        'secret_key' => 'sk_live_abc',
    ])->assertOk()
        ->assertJsonPath('data.enabled', true)
        ->assertJsonPath('data.publishable_key', 'pk_test_123')
        ->assertJsonPath('data.secret_key', 'sk_live_abc');

    expect(Setting::where('namespace', 'business')->where('group', 'stripe')->where('key', 'secret_key')->firstOrFail()->value)
        ->not->toBe('sk_live_abc');

    $this->assertDatabaseHas('settings', [
        'namespace' => 'business',
        'group' => 'stripe',
        'key' => 'enabled',
        'value' => '1',
    ]);
});

it('shows generated integration groups with defaults and select options', function () {
    Passport::actingAs(User::factory()->create());

    $this->getJson('/api/v1/business-settings/paypal')
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.mode', 'sandbox');

    $this->getJson('/api/v1/business-settings')
        ->assertOk()
        ->assertJsonPath('meta.options.paypal.mode', ['sandbox' => 'sandbox', 'live' => 'live'])
        ->assertJsonPath('meta.groups.ai_assistant.label', 'AI Assistant Settings')
        ->assertJsonPath('meta.groups.plaid.description', 'Banking configuration for Plaid Settings.');
});

it('validates select values inside generated integration groups', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/business-settings/paypal', ['mode' => 'production'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mode']);

    $this->putJson('/api/v1/business-settings/webhook', ['enabled' => true, 'webhook_secret' => 'tok_1'])
        ->assertOk()
        ->assertJsonPath('data.webhook_secret', 'tok_1');
});

it('exposes consolidated business and notification settings', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/business-settings/business', [
        'company_email' => 'hello@acme.test',
        'company_website' => 'https://acme.test',
        'company_document' => 'TAX-2026',
    ])->assertOk()
        ->assertJsonPath('data.company_email', 'hello@acme.test')
        ->assertJsonPath('data.company_website', 'https://acme.test')
        ->assertJsonPath('data.company_document', 'TAX-2026');

    $this->putJson('/api/v1/business-settings/prefixes', ['contract' => 'CON'])
        ->assertOk()
        ->assertJsonPath('data.contract', 'CON');

    $this->putJson('/api/v1/business-settings/email_notifications', ['enable_crm_emails' => '1'])
        ->assertOk()
        ->assertJsonPath('data.enable_crm_emails', true);

    $this->putJson('/api/v1/business-settings/time_tracker', ['app_site_url' => 'https://tracker.acme.test'])
        ->assertOk()
        ->assertJsonPath('data.app_site_url', 'https://tracker.acme.test');

    $this->putJson('/api/v1/business-settings/school', ['enable_school_module' => true])
        ->assertOk()
        ->assertJsonPath('data.enable_school_module', true);
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/business-settings')->assertUnauthorized();
    $this->getJson('/api/v1/business-settings/sale')->assertUnauthorized();
    $this->putJson('/api/v1/business-settings/sale', [])->assertUnauthorized();
});
