# Phase 12 — Point of Sale (Core Surface) — Pilot Runbook

This phase documents the core **Point of Sale** surface: sellable tills
(`pos-registers`) and the orders they ring up (`pos-orders`). It is part of the
core staff-authenticated API (not gated by `addon:` middleware); it uses the
standard permission model and `CompanyScoped` concerns like every other core
module.

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET/POST/PUT/PATCH/DELETE /api/v1/pos-registers*`, `POST /api/v1/pos-registers/{id}/restore`
- `GET /api/v1/pos-orders/summary`
- `GET/POST/PUT/PATCH/DELETE /api/v1/pos-orders*`, `POST /api/v1/pos-orders/{id}/restore`
- `GET /api/v1/dashboard/pos-summary` (dashboard card, same aggregates)

## 1. Registers

Module `pos-registers`. `PosRegister` holds `name`, `code`, `warehouse_id`,
`is_active` (defaults `true` on create) and an optional `notes`. `index` eager-loads
`warehouse` and exposes `orders_count`; filters accept `is_active` and a `search` on
`name`. Invariants: `CompanyScoped`, `SoftDeletes`, webhooks, activity.

## 2. Orders

Module `pos-orders`. `PosOrder` uses the `PosOrderStatus` enum
(`completed`, `held`, `refunded`, `cancelled`) and a derived `payment_status`
(`pending`, `partial`, `paid`, `refunded`).

On `store`:
- Line items are normalised by `OrderService::buildLines` and totals by
  `OrderService::computeTotals($lines, $discount_rate)`.
- `order_number` is generated as `POS-YYYYMMDD-00001`; `status` defaults to
  `completed`; `order_date` defaults to today; `cashier_id` defaults to the acting user.
- Tender columns are derived: `paid_amount` = Σ payment amounts,
  `balance_due` = `total − paid`, `payment_status` = `pending` (paid ≤ 0) /
  `paid` (total ≤ 0 or paid ≥ total) / `partial` otherwise.
- Order, items and payments are written inside one `DB::transaction`.

On `update`, replacing items recomputes totals, replacing payments rebuilds the
tender columns from the live payment rows. `index` filters on `status`,
`payment_status`, `register_id`, `contact_id` and searches `order_number`.

Only `completed` orders count towards sales/report figures
(`PosOrder::COUNTED_STATUSES`).

## 3. Summary feed

`PosAnalytics::summary(companyId, date = today, topLimit = 5)` returns
`todays_sales`, `todays_transactions`, `todays_items_sold`, `todays_tax`,
`todays_discount`, `average_sale`, `by_payment_method` and `top_products` — all from
a fixed number of grouped SQL statements (cost independent of order count) and only
`completed` orders. `GET /api/v1/pos-orders/summary` and
`GET /api/v1/dashboard/pos-summary` read the same numbers; both 422 without a
resolvable `company_id`.

## 4. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='PosOrderTest|PosRegisterTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: POS suite 19 passed (126 assertions); `PermissionCoverageTest` 5 passed.
Full suite remains green (1 skipped, 1106 passed).

## 5. Notes

- POS is a core module (`pos-registers`, `pos-orders` are seeded in
  `RolesAndPermissionsSeeder::$modules`); it is not an add-on and has no
  `addon:` gate. `pos-summary` maps to `view-any` in `EnsurePermitted::ACTION_MAP`.
- Existing tests cover company scoping, payload validation, totals/payment
  derivation, delete/restore and permission gating.
