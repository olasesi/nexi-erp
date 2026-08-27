<?php

return [

    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    'traces_sample_rate' => (float) env('SENTRY_TRACES_SAMPLE_RATE', 1.0),

    'release' => env('SENTRY_RELEASE', null),

    'environment' => env('APP_ENV', env('SENTRY_ENVIRONMENT')),

    'breadcrumbs' => [
        'logs' => env('SENTRY_BREADCRUMBS_LOGS', true),
        'cache' => env('SENTRY_BREADCRUMBS_CACHE', true),
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES', true),
        'sql_bindings' => env('SENTRY_BREADCRUMBS_SQL_BINDINGS', false),
        'queue_info' => env('SENTRY_BREADCRUMBS_QUEUE_INFO', true),
        'command_info' => env('SENTRY_BREADCRUMBS_COMMAND_INFO', true),
        'http_client_requests' => env('SENTRY_BREADCRUMBS_HTTP_CLIENT_REQUESTS', true),
    ],

    'tracing' => [
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', false),
        'queue_jobs' => env('SENTRY_TRACE_QUEUE_JOBS', false),
        'sql_queries' => env('SENTRY_TRACE_SQL_QUERIES', false),
        'sql_origin' => env('SENTRY_TRACE_SQL_ORIGIN', false),
        'views' => env('SENTRY_TRACE_VIEWS', false),
        'routes' => env('SENTRY_TRACE_ROUTES', false),
    ],

    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    'capture_silenced_errors' => env('SENTRY_CAPTURE_SILENCED_ERRORS', true),

    'max_request_body_size' => env('SENTRY_MAX_REQUEST_BODY_SIZE', 'medium'),

];
