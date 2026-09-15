<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\BusinessSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;

uses(RefreshDatabase::class);

it('keeps business settings isolated from app settings end-to-end', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/business-settings/sale', ['default_sale_discount' => '12.00'])->assertOk();

    $this->getJson('/api/v1/settings/brand')->assertOk()->assertJsonPath('data.titleText', 'Nexi ERP');

    $this->putJson('/api/v1/settings/brand', ['titleText' => 'Nexi Brand'])
        ->assertOk()
        ->assertJsonPath('data.titleText', 'Nexi Brand');

    $this->getJson('/api/v1/business-settings/sale')->assertOk()->assertJsonPath('data.default_sale_discount', '12.00');

    expect(Setting::where('group', 'sale')->count())->toBe(1)
        ->and(Setting::where('namespace', 'business')->where('group', 'sale')->count())->toBe(1)
        ->and(Setting::where('namespace', 'app')->where('group', 'brand')->count())->toBe(1);
});

it('serves persisted business settings to downstream consumers through the service layer', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/business-settings/prefixes', [
        'purchase' => 'INV',
        'sell_return' => 'RMA',
    ])->assertOk();

    $service = app(BusinessSettingsService::class);
    $purchasePrefix = $service->value(null, 'prefixes', 'purchase');
    $sellReturnPrefix = $service->value(null, 'prefixes', 'sell_return');

    $nextPurchaseReference = fn () => sprintf('%s-%05d', $purchasePrefix, 1);
    $nextReturnReference = fn () => sprintf('%s-%05d', $sellReturnPrefix, 1);

    expect($nextPurchaseReference())->toBe('INV-00001')
        ->and($nextReturnReference())->toBe('RMA-00001');

    $this->putJson('/api/v1/business-settings/modules', [
        'enable_pos' => '1',
        'enable_kitchen' => '0',
    ])->assertOk();

    expect($service->value(null, 'modules', 'enable_pos'))->toBeTrue()
        ->and($service->value(null, 'modules', 'enable_kitchen'))->toBeFalse()
        ->and($service->value(null, 'business', 'time_zone'))->toBe('UTC');
});

it('composes secret handling across the full HTTP - DB - service round trip', function () {
    Passport::actingAs(User::factory()->create());

    $this->putJson('/api/v1/business-settings/email', [
        'mail_driver' => 'smtp',
        'mail_host' => 'smtp.acme.test',
        'mail_port' => 587,
        'mail_password' => 'smtp-secret',
    ])->assertOk();

    $row = Setting::where('namespace', 'business')->where('group', 'email')->where('key', 'mail_password')->firstOrFail();
    expect($row->value)->not->toBe('smtp-secret');

    $group = app(BusinessSettingsService::class)->group(null, 'email');
    expect($group['mail_password'])->toBe('smtp-secret')
        ->and($group['mail_driver'])->toBe('smtp')
        ->and($group['mail_port'])->toBe(587);
});
