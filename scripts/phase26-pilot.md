# Phase 26 — Financial Reports (Core Surface) — Pilot Runbook

This phase documents the core **reporting** endpoints (`reports`): financial
statements and sales/VAT breakdowns computed by `ReportService`.

Routes (core, `auth:api` + `staff` + `permitted`; all map to `reports.view` via
`EnsurePermitted::ACTION_MAP`):
- `GET /api/v1/reports/profit-and-loss`
- `GET /api/v1/reports/balance-sheet`
- `GET /api/v1/reports/cash-flow`
- `GET /api/v1/reports/aging`
- `GET /api/v1/reports/sales-by-product`
- `GET /api/v1/reports/sales-by-category`
- `GET /api/v1/reports/vat-summary`

Every report **requires** `company_id`; period reports take `from`/`to`
(`to` must be `>= from`) and the balance sheet takes `as_of`. Responses are plain
JSON payloads (no pagination).

## 1. Reports

| Endpoint | Inputs | Notes |
| --- | --- | --- |
| `profit-and-loss` | `from`, `to` | Revenue/expense roll-up from posted journal lines |
| `balance-sheet` | `as_of` | Assets vs liabilities/equity; empty by design when no postings exist |
| `cash-flow` | `from`, `to` | Movement across cash-class accounts |
| `aging` | optional `type` (`receivable`/`payable`, default `receivable`) | Buckets outstanding invoice balances and exposes contact **drill-down** detail |
| `sales-by-product` | `from`, `to` | Sales per product, net of credit notes, converted to the base currency |
| `sales-by-category` | `from`, `to` | Sales grouped by category with an **uncategorized** bucket |
| `vat-summary` | `from`, `to` | Tax summarised **per rate**, net of credit notes |

`ReportService` also exposes `monthlySeries`, `monthLabels`, `supportedTypes` and
`aging` helpers reused by the dashboard/analytics layers. Sales figures are
expressed in the company's **base currency** using `CurrencyService` (see
`scripts/phase16-pilot.md`).

## 2. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='FinancialReportTest|SalesReportTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `FinancialReportTest` 5 + `SalesReportTest` 4 — 8 passed, **1 skipped**
(53 assertions). The skipped case is the empty-balance-sheet check (accounts only
appear after the first posting; a base install is empty by design). This is the
suite-wide `1 skipped` seen in full runs. `PermissionCoverageTest` 5 passed.

## 3. Notes

- All report actions resolve to `reports.view`; the module is seeded with
  `dashboard` and `notifications` in the core module list.
- Reports read `journal_entries`/`journal_entry_lines` (accounting, Phase 22) and
  invoice/payment data (Phases 19–20); running the accounting test seeds ensures
  non-empty output.
- Multi-currency amounts are normalised to the company base currency before
  aggregation, so mix-currency companies still get a single comparable figure.
