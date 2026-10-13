# Phase 18 — Purchasing (Core Surface) — Pilot Runbook

This phase documents the core **Purchasing** flow: purchase orders
(`purchase-orders`) and the goods receipts recorded against them
(`purchase-receipts`). Both are core staff-authenticated API surfaces (no
`addon:` middleware); receiving moves stock through `InventoryService`.

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET/POST/PUT/PATCH/DELETE /api/v1/purchase-orders*`
- `POST /api/v1/purchase-orders/{id}/restore`
- `POST /api/v1/purchase-orders/{id}/receive` (record a goods receipt)
- `GET /api/v1/purchase-receipts`, `GET /api/v1/purchase-receipts/{id}`,
  `DELETE /api/v1/purchase-receipts/{id}`

## 1. Purchase orders

Module `purchase-orders`. A `PurchaseOrder` carries `contact_id`, `warehouse_id`,
`order_number`, `status`, `payment_status`, money totals (`subtotal`, `tax_amount`,
`discount_amount`, `discount_rate`, `total`, `paid_amount`, `balance_due`),
`currency`, `notes`, `terms`, `order_date` and `expected_date`.

- `status` ∈ `draft`, `confirmed`, `processing`, `delivered`, `cancelled`;
  `payment_status` ∈ `pending`, `partial`, `paid`, `overdue`, `refunded`.
- `store`/`update` accept an `items` array; `OrderService::buildLines` +
  `computeTotals` derive per-line and header totals from `discount_rate`, and
  `syncItems` replaces lines on update. `order_number` is generated as
  `PO-YYYYMMDD-00001`; unset `status`/`payment_status` default to
  `draft`/`pending`.
- `index` eager-loads `items`/`contact`, filters by `status` / `payment_status` /
  `contact_id`, and searches `order_number`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`,
and a model `updating` hook that calls
`InventoryService::processPurchaseOrderStatusChange` whenever `status` changes:
moving a non-`delivered` order to `delivered` bumps stock for every line
(`receiveStock`), while `cancelled`/`refunded` from `delivered` reverses it
(`reverseStockReceipt`).

## 2. Receiving goods

`POST /purchase-orders/{id}/receive` (mapped to `update` via
`EnsurePermitted::ACTION_MAP`) is rejected with **422** for `draft`, `cancelled` and
`refunded` orders ("Only confirmed purchase orders can be received."). For each
submitted `purchase_order_item_id` + `quantity_received`:

- the outstanding quantity is `quantity − Σ quantity_received` for that PO line;
  receiving more than the outstanding amount raises a validation error;
- `InventoryService::receiveStockFor` increments the `Inventory.quantity` row for
  the product at `warehouse_id` (falling back to the PO's warehouse), creating it
  on demand;
- a `PurchaseReceipt` (`receipt_number` `RCPT-YYYYMMDD-00001`, `status` `received`)
  and its `PurchaseReceiptItem` rows are written in one transaction.

When every PO line is fully received, the order is auto-completed to `delivered`
via `updateQuietly` (no second stock movement). A `purchase_receipt.received`
webhook is dispatched. Response is **201** with the receipt.

## 3. Purchase receipts

Module `purchase-receipts`. `index` filters by `company_id` /
`purchase_order_id` / `status` and matches `receipt_number`; `show` loads the order
and item products. `destroy` calls `InventoryService::reverseReceipt`, which
decrements the received quantities back out of stock and reopens the parent order
(`delivered` → `confirmed`) before deleting the receipt (**204**). Receipts hold
`receipt_number`, `status` (`received`), `received_date`, `warehouse_id` and
`notes`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`.

## 4. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='PurchaseOrderTest|PurchaseReceivingTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `PurchaseOrderTest` 7 + `PurchaseReceivingTest` 5 — 12 passed (38
assertions). `PermissionCoverageTest` 5 passed. Full suite remains green.

## 5. Notes

- `receive` is in `EnsurePermitted::ACTION_MAP` (`'receive' => 'update'`); a
  purchase-receipt's `destroy` uses the `purchase-receipts` `delete` permission.
- The `receive` endpoint creates the receipt inside a DB transaction, then applies
  the auto-complete `updateQuietly`, so the `PurchaseOrder` `updating` hook does not
  re-run `processPurchaseOrderStatusChange` and double-count stock.
- Both modules are core (`RolesAndPermissionsSeeder::$modules`), separate from the
  `product-unit` add-on.
