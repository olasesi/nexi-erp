<?php

use App\Models\Company;
use App\Models\Setting;
use App\Services\BusinessSettingsService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns typed defaults for every group', function () {
    $service = app(BusinessSettingsService::class);

    $values = $service->values();

    expect($values)->toHaveKey('business')
        ->and($values['business']['currency'])->toBe('USD')
        ->and($values['business']['default_profit_percent'])->toBe('25.00')
        ->and($values['business']['transaction_edit_days'])->toBe(30)
        ->and($values['modules']['enable_pos'])->toBeTrue()
        ->and($values['pos']['shortcut_express_checkout'])->toBe('shift+e')
        ->and($values['prefixes']['purchase'])->toBe('PO')
        ->and($values['email']['mail_password'])->toBe('');
});

it('normalizes and persists boolean toggles', function () {
    $service = app(BusinessSettingsService::class);

    $service->set(null, 'pos', [
        'disable_discount' => 'on',
        'subtotal_editable' => true,
        'disable_suspend_sale' => 'off',
    ]);

    expect(Setting::where('namespace', 'business')->where('group', 'pos')->where('key', 'disable_discount')->first()->value)->toBe('1')
        ->and(Setting::where('namespace', 'business')->where('group', 'pos')->where('key', 'subtotal_editable')->first()->value)->toBe('1')
        ->and(Setting::where('namespace', 'business')->where('group', 'pos')->where('key', 'disable_suspend_sale')->first()->value)->toBe('0');

    $pos = $service->group(null, 'pos');

    expect($pos['disable_discount'])->toBeTrue()
        ->and($pos['subtotal_editable'])->toBeTrue()
        ->and($pos['disable_suspend_sale'])->toBeFalse();
});

it('persists integers normalized and reads them back as numbers', function () {
    $service = app(BusinessSettingsService::class);

    $service->set(null, 'business', ['transaction_edit_days' => 15]);

    expect(Setting::where('namespace', 'business')->where('group', 'business')->where('key', 'transaction_edit_days')->first()->value)->toBe('15')
        ->and($service->group(null, 'business')['transaction_edit_days'])->toBe(15);
});

it('persists and reads back arrays, multi selects and objects', function () {
    $service = app(BusinessSettingsService::class);

    $service->set(null, 'payment', ['enable_cash_denomination_for_payment_methods' => ['cash', 'card', 'custom_1']]);
    $service->set(null, 'custom_labels', ['product_custom_field_1' => ['label' => 'Serial No', 'field_type' => 'date']]);

    $paymentRow = Setting::where('namespace', 'business')->where('group', 'payment')->where('key', 'enable_cash_denomination_for_payment_methods')->first();
    expect(json_decode($paymentRow->value, true))->toBe(['cash', 'card', 'custom_1']);

    $group = $service->group(null, 'payment');
    expect($group['enable_cash_denomination_for_payment_methods'])->toBe(['cash', 'card', 'custom_1']);

    $labels = $service->group(null, 'custom_labels');
    expect($labels['product_custom_field_1'])->toBe(['label' => 'Serial No', 'field_type' => 'date'])
        ->and($labels['custom_payment_1'])->toBe('Custom Payment 1');
});

it('encrypts secret keys at rest and returns them decrypted', function () {
    $service = app(BusinessSettingsService::class);

    $service->set(null, 'email', ['mail_password' => 'smtp-secret']);

    $row = Setting::where('namespace', 'business')->where('group', 'email')->where('key', 'mail_password')->firstOrFail();
    expect($row->value)->not->toBe('smtp-secret')
        ->and($service->group(null, 'email')['mail_password'])->toBe('smtp-secret');

    $service->set(null, 'stripe', ['secret_key' => 'sk_live_abc']);
    $stripeRow = Setting::where('namespace', 'business')->where('group', 'stripe')->where('key', 'secret_key')->firstOrFail();
    expect($stripeRow->value)->not->toBe('sk_live_abc')
        ->and($service->group(null, 'stripe')['secret_key'])->toBe('sk_live_abc');
});

it('merges global defaults, global rows and company overrides', function () {
    $service = app(BusinessSettingsService::class);

    $service->set(null, 'business', ['business_name' => 'Global Co']);
    $company = Company::factory()->create();
    $service->set($company->id, 'business', ['business_name' => 'Acme Corp', 'currency' => 'EUR']);

    $global = $service->group(null, 'business');
    expect($global['business_name'])->toBe('Global Co')
        ->and($global['currency'])->toBe('USD');

    $companyGroup = $service->group($company->id, 'business');
    expect($companyGroup['business_name'])->toBe('Acme Corp')
        ->and($companyGroup['currency'])->toBe('EUR')
        ->and($companyGroup['transaction_edit_days'])->toBe(30);
});

it('isolates business settings from the app settings namespace', function () {
    $service = app(BusinessSettingsService::class);
    $settings = app(SettingsService::class);

    $service->set(null, 'email', ['mail_password' => 'biz']);
    $settings->set(null, 'email', ['mailPassword' => 'app-secret']);

    expect(Setting::where('namespace', 'business')->where('group', 'email')->where('key', 'mail_password')->count())->toBe(1)
        ->and(Setting::where('namespace', 'app')->where('group', 'email')->where('key', 'mailPassword')->count())->toBe(1)
        ->and($service->group(null, 'email')['mail_password'])->toBe('biz')
        ->and($service->value(null, 'sale', 'default_sale_discount'))->toBe('10.00');
});

it('exposes a typed single-value lookup for consumers', function () {
    $service = app(BusinessSettingsService::class);

    $service->set(null, 'prefixes', ['purchase' => 'INV']);

    expect($service->value(null, 'prefixes', 'purchase'))->toBe('INV')
        ->and($service->value(null, 'modules', 'enable_pos'))->toBeTrue()
        ->and($service->value(null, 'business', 'transaction_edit_days'))->toBe(30)
        ->and($service->value(null, 'unknown', 'nope'))->toBeNull();
});

it('resolves option lists for selects', function () {
    $service = app(BusinessSettingsService::class);

    $currencies = $service->optionsFor('business', 'currency');
    expect($currencies)->toHaveKey('USD')
        ->and($currencies)->toHaveKey('EUR')
        ->and(array_key_first($currencies))->toBe('USD');

    expect($service->optionsFor('business', 'time_zone'))->toHaveKey('UTC')
        ->and($service->optionsFor('pos', 'shortcut_draft'))->toBe([]);
});

it('provides group navigation meta', function () {
    $meta = app(BusinessSettingsService::class)->meta();

    expect(count($meta))->toBe(count(config('business-settings.groups')))
        ->and($meta['business']['label'])->toBe('Business')
        ->and($meta['custom_labels']['description'])->not->toBe('');
});
