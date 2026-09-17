<?php

use App\Support\IntegrationGroupDefinitions;

it('builds typed settings groups from a provider catalog', function () {
    $catalog = [
        'categories' => [
            'payment_gateway' => 'Payment Gateways',
            'sms' => 'SMS Providers',
        ],
        'providers' => [
            'stripe' => [
                'category' => 'payment_gateway',
                'label' => 'Stripe Settings',
                'description' => 'Accept payments via Stripe.',
                'fields' => [
                    'publishable_key' => ['label' => 'Publishable Key', 'type' => 'secret'],
                    'secret_key' => ['label' => 'Secret Key', 'type' => 'secret'],
                    'mode' => ['label' => 'Mode', 'type' => 'select', 'options' => ['sandbox', 'live'], 'default' => 'sandbox'],
                ],
            ],
            'twilio' => [
                'category' => 'sms',
                'label' => 'Twilio Settings',
                'description' => 'Send SMS via Twilio.',
                'fields' => [
                    'account_sid' => ['label' => 'Account SID', 'type' => 'secret'],
                    'auth_token' => ['label' => 'Auth Token', 'type' => 'secret'],
                    'from' => ['label' => 'From Number', 'type' => 'string'],
                ],
            ],
        ],
    ];

    $groups = IntegrationGroupDefinitions::groups($catalog);

    expect($groups)->toHaveKey('stripe')
        ->and($groups)->toHaveKey('twilio')
        ->and($groups['stripe']['label'])->toBe('Stripe Settings')
        ->and($groups['stripe']['description'])->toContain('Payment Gateways');

    expect($groups['stripe']['keys']['enabled']['type'])->toBe('boolean')
        ->and($groups['stripe']['keys']['enabled']['default'])->toBe('0')
        ->and($groups['stripe']['keys']['publishable_key']['type'])->toBe('secret')
        ->and($groups['stripe']['keys']['secret_key']['type'])->toBe('secret')
        ->and($groups['stripe']['keys']['mode']['type'])->toBe('select')
        ->and($groups['stripe']['keys']['mode']['options'])->toBe(['sandbox', 'live']);

    expect($groups['twilio']['keys']['from']['type'])->toBe('string')
        ->and($groups['twilio']['keys']['account_sid']['type'])->toBe('secret');
});

it('produces consistent groups for the real integration catalog', function () {
    $catalog = config('business-integrations');
    $categories = $catalog['categories'];

    $groups = IntegrationGroupDefinitions::groups($catalog);

    expect($groups)->not->toBeEmpty()
        ->and(count($groups))->toBeGreaterThanOrEqual(20);

    foreach ($groups as $name => $group) {
        expect($group['keys'])->toHaveKey('enabled')
            ->and($group['keys']['enabled']['type'])->toBe('boolean')
            ->and($group['keys']['enabled']['default'])->toBe('0')
            ->and(count($group['keys']))->toBeGreaterThanOrEqual(2)
            ->and($categories)->toHaveKey(config("business-integrations.providers.{$name}.category"));
    }
});

it('keeps every credentials field non-boolean and defaulted for safety', function () {
    $catalog = config('business-integrations');

    $groups = IntegrationGroupDefinitions::groups($catalog);

    foreach ($groups as $group) {
        foreach ($group['keys'] as $key => $definition) {
            if ($key === 'enabled') {
                continue;
            }

            if ($definition['type'] === 'select') {
                expect($definition['options'])->toBeArray()->not->toBeEmpty();
            }

            expect(array_key_exists('default', $definition))->toBeTrue();
        }
    }
});
