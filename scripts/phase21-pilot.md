# Phase 21 — Expenses & Income (Core Surface) — Pilot Runbook

This phase documents the core **Expenses & Income** surfaces: expense categories
(`expense-categories`), expenses (`expenses`), income categories
(`income-categories`) and incomes (`incomes`). All are core staff-authenticated
API surfaces (no `addon:` middleware) and simple normalised records — they do
**not** post to the double-entry ledger (that lives in Phase 19/20 accounting).

Routes (core, `auth:api` + `staff` + `permitted`), each an `apiResource` plus
`POST .../restore`:
- `expense-categories`, `expenses`, `income-categories`, `incomes`

## 1. Categories

Modules `expense-categories` / `income-categories`. `ExpenseCategory` /
`IncomeCategory` hold `company_id`, `name` and `description`; each `HasMany` its
records.

- Unlike most core modules these controllers validate **inline** (no dedicated
  `FormRequest`): `store` requires `name` + `company_id`, `update` treats every
  field as nullable.
- `index` filters by `company_id` and searches `name`.

Invariants: `CompanyScoped`, `SoftDeletes`.

## 2. Expenses

Module `expenses`. `Expense` holds `company_id`, `expense_category_id`,
`reference_no`, `amount` (`decimal:2`), `date`, `payment_method`, `notes` and
`created_by`.

- `store` auto-generates `reference_no` as `EXP-YYMM-0001` when omitted (count of
  this month's existing references + 1) and stamps `created_by` from the
  authenticated user. `reference_no` is unique when supplied.
- `index` eager-loads `expenseCategory`, filters by `expense_category_id`, applies
  an optional `from_date` / `to_date` range on `date`, and searches
  `reference_no` / `notes`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`.

## 3. Incomes

Module `incomes`. `Income` is the exact mirror of `Expense` with
`income_category_id` and `incomeCategory`; `store` auto-generates `reference_no`
as `INC-YYMM-0001`, stamps `created_by`, and `index` behaves identically
(category filter, `from_date`/`to_date`, search `reference_no`/`notes`).

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`.

## 4. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='ExpenseCategoryTest|ExpenseTest|IncomeCategoryTest|IncomeTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `ExpenseCategoryTest` 7 + `ExpenseTest` 9 + `IncomeCategoryTest` 7 +
`IncomeTest` 9 — 32 passed (74 assertions). `PermissionCoverageTest` 5 passed.
Full suite remains green.

## 5. Notes

- Reference numbers (`EXP-YYMM-0001` / `INC-YYMM-0001`) are generated per company
  scope by counting the month's matching rows, matching the quotation scheme in
  `scripts/phase19-pilot.md`.
- These records are reporting inputs only; nothing here writes `journal_entries`
  or touches `Inventory`. Ledger effects come from invoices/payments
  (`AccountingService`) documented in Phases 19–20.
