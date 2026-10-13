# Phase 16 — Currencies & FX (Core Surface) — Pilot Runbook

This phase documents the core **Currency** surface: the supported ISO-4217 list
(`currencies`) and company-level exchange rates (`currency-rates`), plus the
`CurrencyService` that converts and exposes FX totals on documents. Both are part of
the core staff-authenticated API (not gated by `addon:` middleware).

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET /api/v1/currencies`
- `GET/POST/PUT/PATCH/DELETE /api/v1/currency-rates*`, `POST /api/v1/currency-rates/{id}/restore`

## 1. Supported currencies

Module `currencies` (partial, `view-any` only). `CurrenciesController::index` reads
the static `config('currencies')` list (155 ISO-4217 entries; each row is
`[id, name, code, symbol, is_default]`) and returns
`{data: [{id, name, code, symbol, is_default}], meta: {total}}`. The default currency
is sorted first; an optional `?search=` filters by name or code (case-insensitive).
There is no write surface.

## 2. Currency rates

Module `currency-rates`. `CurrencyRate` holds `company_id`, `base_currency`,
`currency` and `rate` (`decimal:6`). Invariants: `CompanyScoped`, `SoftDeletes`.

- `store` requires `currency` (3 chars, different from `base_currency`) and
  `rate > 0`; `base_currency` defaults to the company base from `CurrencyService`.
  Codes are upper-cased and the row is **upserted** on
  `(company_id, base_currency, currency)`, so re-posting the same pair updates the
  rate in place rather than duplicating it.
- Full CRUD is available; `restore` re-instates a soft-deleted rate and returns 422
  when the record is not trashed.
- `index` filters by `company_id`.

## 3. CurrencyService

- `baseCurrency(?companyId)` — the `is_default` currency code from config (falls back
  to `USD`).
- `toBase(currency, companyId)` — 1.0 for the base currency, otherwise the matching
  `currency_rates` row, defaulting to 1.0 when none exists.
- `rate(from, to, ?companyId)` — 1.0 when `from === to` or no company is given;
  otherwise `toBase(from) / max(toBase(to), 0.000001)`.
- `convert(amount, from, to, ?companyId)` — `amount × rate`, rounded to 2 dp.
- `fx(currency, total, ?companyId)` — `{base_currency, rate, base_total}` summary used
  by document resources (invoices, sales orders) to expose the base-currency
  equivalent alongside the document total.

## 4. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes config\currencies.php
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='CurrenciesTest|CurrencyRateTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `CurrenciesTest` 3 + `CurrencyRateTest` 6 — 9 passed (32 assertions);
`PermissionCoverageTest` 5 passed. Full suite remains green.

## 5. Notes

- `currencies` is seeded as a partial module (`view-any` only) and `currency-rates`
  is a full module in `RolesAndPermissionsSeeder`. `restore` maps to `update` in
  `EnsurePermitted::ACTION_MAP`.
- `currency_rate` is on `WebhookService::IGNORED_SUBJECTS`, so rate changes never
  emit webhook deliveries.
