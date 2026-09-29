<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Dispatch driver
    |--------------------------------------------------------------------------
    |
    | "sync" delivers webhooks in-process, which keeps local/XAMPP installs
    | working without a queue worker. "queue" pushes each delivery onto the
    | queue (see WEBHOOK_QUEUE) so requests stay fast and retries survive
    | restarts.
    |
    */

    'driver' => env('WEBHOOK_DRIVER', 'sync'),

    'queue' => env('WEBHOOK_QUEUE', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Delivery tuning
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('WEBHOOK_TIMEOUT', 10),

    'max_attempts' => (int) env('WEBHOOK_MAX_ATTEMPTS', 4),

    /*
    | Seconds to wait before each retry; the last entry is reused once the
    | schedule is exhausted.
    |
    */

    'backoff' => [60, 300, 900, 3600],

    'user_agent' => env('WEBHOOK_USER_AGENT', 'NexiERP-Webhooks'),

    /*
    | Seconds a signature timestamp stays valid for replay protection.
    |
    */

    'signature_tolerance' => (int) env('WEBHOOK_SIGNATURE_TOLERANCE', 300),

    /*
    | Characters of the endpoint response kept on the delivery record.
    |
    */

    'response_limit' => (int) env('WEBHOOK_RESPONSE_LIMIT', 2000),

    /*
    |--------------------------------------------------------------------------
    | Delivery log retention
    |--------------------------------------------------------------------------
    |
    | Days a finished delivery is kept before the webhooks:prune command is
    | allowed to delete it. Pending deliveries are never pruned.
    |
    */

    'retention_days' => (int) env('WEBHOOK_RETENTION_DAYS', 30),
];
