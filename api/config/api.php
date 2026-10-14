<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default version
    |--------------------------------------------------------------------------
    |
    | Version served for requests that do not carry one, and the one reported
    | by the discovery endpoint.
    |
    */

    'default' => env('API_DEFAULT_VERSION', 'v1'),

    /*
    |--------------------------------------------------------------------------
    | Response header
    |--------------------------------------------------------------------------
    */

    'header' => env('API_VERSION_HEADER', 'X-Api-Version'),

    /*
    |--------------------------------------------------------------------------
    | URL prefix and media type
    |--------------------------------------------------------------------------
    |
    | Requests select a version through the /api/{version}/... path segment,
    | the Accept header (application/vnd.nexi.v1+json) or the version header.
    |
    */

    'path_prefix' => 'api',

    'media_type' => env('API_MEDIA_TYPE', 'application/vnd.nexi'),

    /*
    |--------------------------------------------------------------------------
    | Published versions
    |--------------------------------------------------------------------------
    |
    | Each entry is keyed by version. `status` is stable, deprecated or sunset.
    | A deprecated version keeps answering but advertises its replacement with
    | the Deprecation, Sunset and Link headers, and a version whose sunset date
    | has passed is answered with 410 Gone.
    |
    */

    'versions' => [
        'v1' => [
            'status' => 'stable',
            'sunset_at' => null,
            'replaced_by' => null,
            'notice' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limit (requests per minute)
    |--------------------------------------------------------------------------
    |
    | Applied by the `throttle:api` middleware to every route in the API group.
    | Requests are bucketed per authenticated user, falling back to the client
    | IP for guests. Raise this behind a trusted proxy or lower it to tighten
    | abuse protection. Tests raise the limit through API_RATE_LIMIT in
    | phpunit.xml so suite-wide fixtures do not trip the limiter.
    |
    */

    'rate_limit' => (int) env('API_RATE_LIMIT', 120),
];
