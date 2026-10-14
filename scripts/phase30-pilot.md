# Phase 30 — Dashboard (core) — Pilot Runbook

This phase documents the **core Dashboard** controller: the KPI summary plus the
per-card read-only feeds the home screen is built from. Every route is a `GET`
guarded by `dashboard.view-any` on the acting user's company.

Routes (`DashboardController`), all gated on **`dashboard.view-any`** (the
`summary` action and every card-feed action map onto `view-any`):
- `GET /api/v1/dashboard` — the KPI summary (`dashboard.summary`)
- `GET /api/v1/dashboard/financial-year-sales`
- `GET /api/v1/dashboard/sales-payment-due`
- `GET /api/v1/dashboard/purchase-payment-due`
- `GET /api/v1/dashboard/stock-alert`
- `GET /api/v1/dashboard/sales-orders`
- `GET /api/v1/dashboard/pending-shipments`
- `GET /api/v1/dashboard/crm-summary`
- `GET /api/v1/dashboard/project-summary`
- `GET /api/v1/dashboard/pos-summary`

Every route validates `company_id` (`required|exists`); the `limit`-bearing feeds
accept `limit` (`1–100`, default 10).

## 1. KPI summary (`dashboard`)

`COUNTED_STATUSES = paid, partial, sent, overdue` gate every document total.
Receivables use `invoice`/`credit_note`, payables use `bill`/`debit_note`.

- `month_revenue`/`month_expense`/`month_net_profit` are **pinned to the current
  month** (WorkDo-style) so existing consumers keep their meaning.
- The **filtered card block** honours `from`/`to` (defaulting to the current
  month): `total_sales`, `total_sell_return`, `total_purchase`,
  `total_purchase_return`, `expense`, and `daily_sales`.
- `net` follows the POS card: `sales − outstanding_receivables − period_expense`.
- An **inverted range is rejected** (`from` after `to` → `422`).
- `daily_sales` is a gapless series (empty days emitted as `0`, credit notes
  subtract) **capped at `MAX_SERIES_DAYS` (365)** so a wide range cannot balloon
  the payload.
- Also returns contact counts, completed customer/vendor payments, open sales
  orders, low-stock and total product counts, `recent_revenue`, six-month
  `monthly_customer_payments`/`monthly_vendor_payments` and `recent_revenues`/
  `recent_expenses` (latest posted `revenue`/`expense` journal lines).

## 2. Financial year sales

`salesPaymentDue`… no: `financialYearSales` builds a **twelve-point monthly
series** for the financial year containing today. The start month comes from
business settings (`financial_year_start_month`, default January) and buckets are
offset from the fiscal start so a year straddling two calendar years still lines
up. Each point carries `sales`, `sell_return` and `net`;
`financial_year.label` is the plain year when the year starts in January, else
`YYYY/YYYY+1`.

## 3. Payment due cards

`salesPaymentDue` (invoices) and `purchasePaymentDue` (`bill`/`debit_note`) share
`paymentDue`: unpaid documents (`sent`/`partial`/`overdue`, `balance_due > 0`)
falling due within `days` (default `DEFAULT_DUE_WINDOW_DAYS` 7, max 365), ordered
by `due_date`. Responds with `window_days`, `due_before`, `total`, `total_due` and
each row's `is_overdue`/`days_overdue`.

## 4. Card feeds

- **`stock-alert`** — active `type = product` rows at or below
  `min_stock_level`, least stock first, with `shortfall` and `is_out_of_stock`.
- **`sales-orders`** — recent orders (with contact/warehouse/author) plus the
  `quantity_remaining` derived from invoiced quantities (`max(0, ordered −
  invoiced)`).
- **`pending-shipments`** — `invoice` rows in `Invoice::OPEN_SHIPPING_STATUSES`
  (not draft/cancelled), newest first, with the responsible service staff.
- **`crm-summary`** — `CrmAnalytics::summary` plus active follow-ups and top open
  deals, funnel by status and leads by source.
- **`project-summary`** — `ProjectAnalytics::summary` plus the project portfolio.
- **`pos-summary`** — `PosAnalytics::summary` for a trading day (`date`,
  `limit` `1–50`) — takings, transaction count, payment mix, best sellers.

## 5. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter=DashboardTest
php artisan test --filter=PermissionCoverageTest
```

Expected: `DashboardTest` 23 passed (199 assertions). `PermissionCoverageTest` 5
passed. Full suite remains green.

## 6. Notes

- `month_*` fields deliberately stay on the calendar month even when the card
  block is filtered; only the fields under `period` honour `from`/`to`.
- Each card is independently limit-capped, so a busy ledger cannot return an
  unbounded page.
- CRM/project/POS cards delegate their aggregation to the dedicated analytics
  services (`CrmAnalytics`, `ProjectAnalytics`, `PosAnalytics`).
