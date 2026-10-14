# Phase 27 — Smart Dashboard Analytics (Add-on `smart-dashboard`) — Pilot Runbook

This phase documents the **Smart Dashboard** add-on (`smart-dashboard`): four
read-only aggregate "tabs" computed and cached by `AnalyticsService`.

Routes (add-on `smart-dashboard`, then `analytics.view`):
- `GET /api/v1/analytics/executive`
- `GET /api/v1/analytics/financial`
- `GET /api/v1/analytics/sales`
- `GET /api/v1/analytics/operations`

## 1. Contract

Every tab takes a `company_id` (required), an optional `limit`
(`1..analytics.max_limit`, default `25`) and an optional `fresh` boolean. The
add-on answers `403 {code:"addon_disabled"}` before the permission check when the
company has switched it off; unpermissioned users get a `403`.

| Add-on id | Module | Actions |
| --- | --- | --- |
| `smart-dashboard` | `analytics` | `view` (enabled by default) |

## 2. Tab payloads

`AnalyticsService::tab($tab, $companyId, $params)` dispatches to:

- **executive** — KPIs (revenue, margin, conversion, average daily) with period
  vs previous-period deltas, module summaries, top customers and recent revenue
  entries.
- `financial` — revenue vs purchases, cash balance, pending documents, stock value.
- `sales` — sales vs purchases trend and per-category figures.
- `operations` — stock health: out-of-stock/low-stock lists, stock by category and
  warehouse, purchase summary and product list.

Payloads are scoped to the requested `company_id`; every tab shares the one
`analytics.view` permission. Embedded lists are capped by `default_limit` (25) and
`max_limit` (100) from `config/analytics.php`; full record browsing stays on each
module's own paginated endpoints.

## 3. Caching

`config/analytics.php` sets `cache_ttl` (env `ANALYTICS_CACHE_TTL`, default `300`
seconds; `0` disables caching). Cached reads are keyed per tab/company/period/limit;
passing `fresh=1` bypasses the cache for that request. Set `ANALYTICS_CACHE_TTL=0`
in tests so every request recomputes.

## 4. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter=AnalyticsTest
php artisan test --filter=PermissionCoverageTest
```

Expected: `AnalyticsTest` 11 passed (188 assertions). `PermissionCoverageTest` 5
passed. Full suite remains green.

## 5. Notes

- The add-on id is `smart-dashboard` (distinct from the `smart-reports` add-on in
  `scripts/phase1-pilot.md`); its permission `analytics.view` is seeded from the
  same `config/addons.php` entry.
- All tabs are read-only aggregates over the accounting, sales, purchase and
  inventory data documented in Phases 14–22.
