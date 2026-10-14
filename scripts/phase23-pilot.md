# Phase 23 — Companies & Business Locations (Core Surface) — Pilot Runbook

This phase documents the core tenancy surfaces: **companies** (`companies`) and
**business locations** (`business-locations`). Both are core staff-authenticated
API surfaces (no `addon:` middleware).

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET/POST/PUT/PATCH/DELETE /api/v1/companies*`, `POST /api/v1/companies/{id}/restore`
- `GET/POST/PUT/PATCH/DELETE /api/v1/business-locations*`, `POST /api/v1/business-locations/{id}/restore`

## 1. Companies

Module `companies`. `Company` is the **tenant root** — it is *not*
`CompanyScoped` (it has no `company_id`), only `HasFactory`, `SoftDeletes`.

- Fields: `name` (required), `legal_name`, `email`, `phone`, `tax_id`,
  `registration_number`, `website`, `notes`, `is_active`. It exposes `HasMany`
  relations to `contacts`, `products`, `productCategories`, `warehouses`,
  `salesOrders`, `purchaseOrders`, `projects`, `posRegisters`, `posOrders`, etc.
- `index` filters by `is_active` and searches `name` / `legal_name` / `email` /
  `tax_id`.

Invariants: `SoftDeletes` (restorable).

## 2. Business locations

Module `business-locations`. `BusinessLocation` holds `company_id`, `name`,
`location_id`, `landmark`, `city`, `zip_code`, `state`, `country`, `price_group`,
`invoice_scheme`, `invoice_layout_for_pos` and `invoice_layout_for_sale`.

- `store` auto-generates `location_id` when omitted: it reads the `business-locations`
  prefix from `BusinessSettingsService` (`prefixes.location_id`, default `BL`) and
  emits `{prefix}-0001` counting the company's existing locations.
  `location_id` is unique per company when supplied.
- `index` filters by `city` / `state` / `country` / `price_group` and searches
  `name` / `location_id` / `landmark` / `city` / `state` / `country`.

Invariants: `CompanyScoped`, `SoftDeletes`.

## 3. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='CompanyTest|BusinessLocationTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `CompanyTest` 8 + `BusinessLocationTest` 11 — 19 passed (58 assertions).
`PermissionCoverageTest` 5 passed. Full suite remains green.

## 4. Notes

- `Company` is the only core model that is **not** `CompanyScoped` — every other
  scoped model hangs off a company via `company_id`.
- The generated `location_id` prefix is customer-configurable through business
  settings (`scripts/settings-pilot.md`), so location ids are stable per tenant.
