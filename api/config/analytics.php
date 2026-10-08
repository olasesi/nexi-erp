<?php

/*
|--------------------------------------------------------------------------
| Smart Dashboard analytics
|--------------------------------------------------------------------------
| Controls the four Smart Dashboard tab payloads (app/Services/
| AnalyticsService.php).
|
| cache_ttl      Seconds a tab payload stays cached per company, period and
|                page. Cached reads are what keep the dashboard cheap enough
|                to poll; set ANALYTICS_CACHE_TTL=0 to disable caching (every
|                request recomputes, which is what you want in tests).
| default_limit  Rows in an embedded list (transactions, products).
| max_limit      Upper bound on that list. Full record browsing stays on the
|                module's own paginated list endpoints.
*/

return [
    'cache_ttl' => (int) env('ANALYTICS_CACHE_TTL', 300),
    'default_limit' => 25,
    'max_limit' => 100,
];
