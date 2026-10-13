# Phase 17 — Products & Categories (Core Surface) — Pilot Runbook

This phase documents the core **Product catalogue**: product categories
(`product-categories`) and products (`products`), including CSV import/export. Both
are part of the core staff-authenticated API (not gated by `addon:` middleware);
they use the standard permission model and `CompanyScoped` + `SoftDeletes` concerns.

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET/POST/PUT/PATCH/DELETE /api/v1/product-categories*`, `POST /api/v1/product-categories/{id}/restore`
- `GET/POST/PUT/PATCH/DELETE /api/v1/products*`, `POST /api/v1/products/{id}/restore`
- `POST /api/v1/products/import` (create/update from CSV, inline or uploaded)
- `GET /api/v1/products/export` (download the catalogue as CSV)

## 1. Product categories

Module `product-categories`. `ProductCategory` holds `parent_id`, `name`, `slug`,
`description` and `is_active`; a `creating` hook fills `slug` from `name` when blank,
and `parent`/`children`/`products` relations form a tree. `index` filters by
`is_active` / `parent_id` and searches `name`. Invariants: `CompanyScoped`,
`SoftDeletes`.

## 2. Products

Module `products`. `Product` holds `category_id`, `name`, `sku`, `barcode`, `type`,
`unit`, `sale_price`, `purchase_price`, `cost_price`, `tax_rate`, `stock_quantity`,
`min_stock_level`, `description` and `is_active`.

- `type` is one of `product`, `service`, `digital`, `bundle`; `sku` is unique when
  present; `sale_price`/`purchase_price`/`cost_price` are required and `≥ 0`;
  `tax_rate` is `0–100`.
- `index` filters by `category_id` / `type` / `is_active` and searches
  `name`/`sku`/`barcode`.
- `inventories`, `salesOrderItems` and `purchaseOrderItems` relations back the
  inventory ledger documented in `scripts/phase14-pilot.md`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`.

## 3. CSV import / export

- `import` accepts a CSV either inline (`csv` string) or as an uploaded file plus a
  `dry_run` flag. `ProductImporter` normalises each row, validates
  (`name` required; `type` ∈ {product, service}; numeric prices) and returns
  per-row `created` / `updated` / `error` results without aborting the batch. Rows
  are keyed by `sku` within the company: an existing SKU is updated, otherwise a row
  is created. `dry_run` reports the same outcome without persisting.
- `export` streams `products.csv` with the columns
  `id, name, sku, barcode, type, unit, sale_price, purchase_price, stock_quantity,
  min_stock_level`, optionally scoped by `company_id` (`Csv::download`).

## 4. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='ProductTest|ProductCategoryTest'
php artisan test --filter='BulkImportExportTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `ProductTest` 8 + `ProductCategoryTest` 8 — 16 passed (49 assertions). The
shared `BulkImportExportTest` (10 passed, 46 assertions) covers the product
import/export paths (alongside contacts and invoices). `PermissionCoverageTest` 5
passed. Full suite remains green.

## 5. Notes

- `products` and `product-categories` are core modules seeded in
  `RolesAndPermissionsSeeder::$modules`; `import` maps to `create` and `export` to
  `view` in `EnsurePermitted::ACTION_MAP`.
- `product-unit` is a separate opt-in add-on (documented via the add-on table in
  `scripts/features-pilot.md`), not part of this core catalogue.
