# Phase 14 — Stock & Warehousing (Core Surface) — Pilot Runbook

This phase documents the core **Stock** and **Warehousing** surfaces: warehouses
(`warehouses`), stock adjustments (`stock-adjustments`) and stock transfers
(`stock-transfers`). All three are part of the core staff-authenticated API (not
gated by `addon:` middleware); they use the standard permission model and
`CompanyScoped` + `SoftDeletes` concerns.

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET/POST/PUT/PATCH/DELETE /api/v1/warehouses*`, `POST /api/v1/warehouses/{id}/restore`
- `GET/POST/PUT/PATCH/DELETE /api/v1/stock-adjustments*`, `POST /api/v1/stock-adjustments/{id}/restore`
- `GET/POST/PUT/PATCH/DELETE /api/v1/stock-transfers*`, `POST /api/v1/stock-transfers/{id}/restore`
- `POST /api/v1/stock-transfers/{id}/complete`, `POST /api/v1/stock-transfers/{id}/cancel`

## 1. Warehouses

Module `warehouses` (part of the Products sidebar group). `Warehouse` holds `name`,
`code`, `location`, `phone`, `notes` and `is_active`. `index` filters by `is_active`
and searches `name`. Invariants: `CompanyScoped`, `SoftDeletes` (no webhook/activity
concerns on this model).

## 2. Stock adjustments

Module `stock-adjustments`. `StockAdjustment` holds `warehouse_id`, `reference_no`,
`reason` and `adjusted_by`; each `StockAdjustmentItem` carries `product_id` +
`quantity`.

- `store` validates `warehouse_id`, a non-empty `items[]` (`product_id`, `quantity ≥ 1`),
  generates `reference_no` as `ADJ-YYMM-0001` (company-agnostic count), stamps
  `adjusted_by` with the acting user, and writes adjustment + items in a transaction.
- Posting an adjustment **increases** inventory for every line (via
  `InventoryService::adjustStock`, `direction = 'increase'`), then emits the domain
  event `stock_adjustment.posted`.
- `destroy` **reverses** the increase (`direction = 'decrease'`) for every line before
  soft-deleting the adjustment.
- Implements `index`/`show`/`store`/`destroy` only (no update).

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`.

## 3. Stock transfers

Module `stock-transfers`. `StockTransfer` holds `from_warehouse_id`,
`to_warehouse_id`, `reference_no`, `status`, `notes` and `transferred_by`; each
`StockTransferItem` carries `product_id` + `quantity`.

- `store` requires two **different** warehouses and a non-empty `items[]`; always
  starts in status `pending`, generates `reference_no` as `TRF-YYMM-0001` and stamps
  `transferred_by`. No stock moves until completion.
- `complete` (422 if already `completed` or if `cancelled`) runs
  `TransferService::completeTransfer`: decrement the source inventory and increment
  the destination for each line, then set `status = completed` and emit the domain
  event `stock_transfer.completed`.
- `cancel` (422 if already `cancelled` or if `completed`; the service can also reverse
  a completed transfer if invoked directly) sets `status = cancelled`.
- `destroy` is allowed only while `pending` (422 otherwise).
- Filters: `from_warehouse_id`, `to_warehouse_id`, `status`; search `reference_no`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`.

## 4. Inventory ledger

`InventoryService` is the shared ledger used by sales orders, purchase receipts and
the stock surfaces above: `reserveStock`/`releaseReservedStock`/`deductStock` for the
sales lifecycle, `receiveStock`/`receiveStockFor`/`reverseReceivedQuantity` for
receipts, and `adjustStock` for adjustments. Every helper `firstOrCreate`s the
`Inventory` row for `(product_id, warehouse_id)` and adjusts `quantity` /
`reserved_quantity`; lines without a `product_id` are ignored.

## 5. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='StockAdjustmentTest|StockTransferTest|WarehouseTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `StockAdjustmentTest` 6 + `StockTransferTest` 8 + `WarehouseTest` 8 — 22
passed (49 assertions); `PermissionCoverageTest` 5 passed. Full suite remains green.

## 6. Notes

- Stock is a core module: `stock-adjustments`, `stock-transfers` and `warehouses` are
  seeded in `RolesAndPermissionsSeeder::$modules`; `complete`/`cancel` map to `update`
  in `EnsurePermitted::ACTION_MAP`.
- The domain events `stock_adjustment.posted` and `stock_transfer.completed` are part
  of the webhook catalogue documented in `scripts/phase13-pilot.md`.
