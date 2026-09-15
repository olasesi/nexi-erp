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

    expect(count($body['data']))->toBe(17)
        ->and(count($body['meta']['groups']))->toBe(17)
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

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();
    $this->flushHeaders();

    $this->getJson('/api/v1/business-settings')->assertUnauthorized();
    $this->getJson('/api/v1/business-settings/sale')->assertUnauthorized();
    $this->putJson('/api/v1/business-settings/sale', [])->assertUnauthorized();
});
