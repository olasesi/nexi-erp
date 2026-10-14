# Phase 19 — Sales & Invoicing (Core Surface) — Pilot Runbook

This phase documents the core **Sales & Invoicing** documents: sales orders
(`sales-orders`), quotations (`quotations`) and invoices (`invoices`, incl. CSV
export, PDF and email). All are core staff-authenticated API surfaces (no
`addon:` middleware) that use `OrderService` for line/total maths and
`InventoryService` / accounting services on status transitions.

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET/POST/PUT/PATCH/DELETE /api/v1/sales-orders*`, `POST /api/v1/sales-orders/{id}/restore`
- `GET/POST/PUT/PATCH/DELETE /api/v1/quotations*`, `POST /api/v1/quotations/{id}/restore`,
  `POST /api/v1/quotations/{id}/accept`, `POST /api/v1/quotations/{id}/reject`
- `GET /api/v1/invoices/export`, `GET/POST/PUT/PATCH/DELETE /api/v1/invoices*`,
  `POST /api/v1/invoices/{id}/restore`, `GET /api/v1/invoices/{id}/pdf`,
  `POST /api/v1/invoices/{id}/email`

## 1. Sales orders

Module `sales-orders`. `SalesOrder` mirrors the purchase-order shape: `contact_id`,
`warehouse_id`, `order_number`, `status`, `payment_status`, money totals,
`currency`, `notes`, `terms`, `order_date`, `expected_date`, plus `created_by`.

- `status` ∈ `draft`, `confirmed`, `processing`, `shipped`, `delivered`,
  `cancelled`, `refunded`; `payment_status` ∈ `pending`, `partial`, `paid`,
  `overdue`, `refunded`.
- `store` builds lines/totals with `OrderService::buildLines`/`computeTotals`,
  generates `order_number` as `SO-YYYYMMDD-00001`, defaults `status` to `draft`,
  `payment_status` to `pending`, and stamps `created_by` from the authenticated
  user.
- `index` eager-loads `items`/`contact`, filters by `status`/`payment_status`/
  `contact_id`, searches `order_number`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`,
and an `updating` hook that runs `InventoryService::processSalesOrderStatusChange`
on status change: `confirmed`/`processing` **reserve** stock, `shipped`/`delivered`
**deduct** it, and `cancelled`/`refunded` **release** reserved stock.

## 2. Quotations

Module `quotations`. `Quotation` carries `contact_id`, `warehouse_id`,
`quotation_no`, `status`, totals, `currency`, `notes`, `valid_until`, etc.
`quotation_no` is generated as `QT-YYMM-0001` (`nextRef` counts rows for the
current year–month), defaulting `status` to `draft`.

- `accept` (mapped to `update`): **422** if already `accepted`, or if `rejected`/
  `expired`. Otherwise sets `status=accepted` and, unless
  `convert_to_sales_order=false` is posted, creates a **draft** `SalesOrder` that
  copies the header totals and every line. Emits `quotation.accepted`.
- `reject` (mapped to `update`): **422** if already `rejected` or if `accepted`;
  otherwise sets `status=rejected`. Emits `quotation.rejected`.
- `destroy` only removes `draft` quotations (**422** otherwise); `restore` reverses
  soft-delete. `index` filters by `contact_id`/`status`, searches `quotation_no`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`.

## 3. Invoices

Module `invoices`. `Invoice` covers both sales documents and payables:
`type` ∈ `invoice`, `credit_note` (sales — `isSales()`), `bill`, `debit_note`
(payable — `isPayable()`).

- `invoice_number` is `INV-YYYYMMDD-00001` for sales types and `BILL-YYYYMMDD-00001`
  for `bill`/`debit_note`; `status` ∈ `draft`, `sent`, `partial`, `paid`,
  `overdue`, `cancelled`. `paid_amount` starts at 0 and `balance_due` = total.
- `shipping_status` walks `SHIPPING_STATUSES`
  (`pending → processing → ready → shipped → partial → delivered`, plus
  `returned`); `hasOpenShipment()` reports the `OPEN_SHIPPING_STATUSES`.
- Accounting side-effects (`syncAccounting`): statuses `sent`/`partial`/`paid`/
  `overdue` post a journal entry via `AccountingService::postInvoice`; `cancelled`
  or a return to `draft` void it via `GeneralLedgerService::voidForSource`.
- `update`: moving a **fully paid** invoice out of `paid` is rejected (**422**).
  `destroy`: rejected with **422** when completed payments exist; otherwise voids
  the source journal and deletes.
- `export` streams `invoices.csv` honouring the same filters as `index`
  (`company_id`/`type`/`status`/`contact_id`, search `invoice_number`).
- `pdf` streams the rendered `pdf.invoice` view; `email` requires a contact email
  (**422** otherwise), sends `InvoiceMail` with the PDF and emits `invoice.sent`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`.
Paid amount/status follow completed payments (see Phase 20 — Payments).

## 4. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='SalesOrderTest|QuotationTest|InvoiceTest|InvoiceExportTest|DocumentDiscountTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `SalesOrderTest` 8 + `QuotationTest` 8 + `InvoiceTest` 7 +
`InvoiceExportTest` 11 + `DocumentDiscountTest` 4 — 38 passed (120 assertions).
`DocumentDiscountTest` covers the document-wide `discount_rate` on sales orders
and invoices (and that the ledger stays balanced). `PermissionCoverageTest` 5
passed. Full suite remains green.

## 5. Notes

- `EnsurePermitted::ACTION_MAP` maps `accept`/`reject`/`email` to `update`,
  `pdf`/`export` to `view` (so custom actions inherit the parent module permission).
- Quotation→sales-order conversion is one-way and always creates a `draft`
  order with `payment_status=pending`; subsequent stock movements happen only when
  that order's status advances.
- The public pay-by-token endpoints (`invoices/pay/{token}`) and customer-portal
  read endpoints (`customer-portal/invoices` etc.) are **public/customer** surfaces
  documented separately from this staff API.
