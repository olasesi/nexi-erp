<?php

use App\Payments\Drivers\StripePaymentGateway;
use App\Payments\Drivers\StubPaymentGateway;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    | Used when a company has no payment-gateway integration connected. The
    | offline stub records payments directly so the surface works in tests and
    | before any provider is wired in.
    */
    'driver' => env('PAYMENT_GATEWAY', 'stub'),

    'drivers' => [
        'stub' => StubPaymentGateway::class,
        'stripe' => StripePaymentGateway::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers that are usable as a checkout gateway
    |--------------------------------------------------------------------------
    | A connected integration only becomes the active gateway when its provider
    | key appears here. Other payment_gateway catalog entries (e.g. regional
    | wallets) stay configuration-only until a driver is registered.
    */
    'checkout_providers' => [
        'stripe',
    ],

    'webhook_tolerance' => (int) env('PAYMENT_WEBHOOK_TOLERANCE', 300),
];
