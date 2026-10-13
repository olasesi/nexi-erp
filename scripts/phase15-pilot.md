# Phase 15 — Banking & Reconciliation (Core Surface) — Pilot Runbook

This phase documents the core **Payment Accounts** surface: bank accounts
(`bank-accounts`), imported statement lines (`bank-transactions`) and the
reconciliations that tie them to the ledger (`reconciliations`). All three are part
of the core staff-authenticated API (not gated by `addon:` middleware); they use the
standard permission model and `CompanyScoped` + `SoftDeletes` concerns.

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET/POST/PUT/PATCH/DELETE /api/v1/bank-accounts*`, `POST /api/v1/bank-accounts/{id}/restore`
- `GET/POST/PUT/PATCH/DELETE /api/v1/bank-transactions*`, `POST /api/v1/bank-transactions/{id}/restore`
- `POST /api/v1/bank-transactions/import`
- `POST /api/v1/bank-transactions/{id}/match`, `POST /api/v1/bank-transactions/{id}/unmatch`
- `GET/POST/PUT/PATCH/DELETE /api/v1/reconciliations*`, `POST /api/v1/reconciliations/{id}/restore`
- `POST /api/v1/reconciliations/{id}/complete`

`EnsurePermitted::ACTION_MAP`: `import` → `create`; `match`/`unmatch`/`complete` → `update`.

## 1. Bank accounts

Module `bank-accounts`. `BankAccount` holds `name`, `bank_name`, `account_number`,
`iban`, `swift`, `account_type`, `currency`, `opening_balance` and `is_active`.
`index` exposes `transactions_count` and filters by `account_type` / `is_active`,
searching `name`/`bank_name`/`account_number`. Invariants: `CompanyScoped`,
`SoftDeletes` (no webhook/activity concerns on this model).

## 2. Bank transactions

Module `bank-transactions`. `BankTransaction` holds `bank_account_id`,
`transaction_date`, `description`, `reference`, `amount`, `category`, `status`
(e.g. `cleared`), `reconciliation_status` and `matched_payment_id`.

- `import` bulk-creates every row in `transactions[]` for one account inside a
  transaction, forcing `status = cleared` and `reconciliation_status = unreconciled`
  (the request dictates `company_id` + `bank_account_id`).
- `match` links a transaction to a **completed** payment (422 otherwise). When
  `abs(abs(txn.amount) − payment.amount) < 0.005` it sets `reconciliation_status =
  matched`; an amount mismatch still stores the link but stays `unreconciled`.
- `unmatch` clears `matched_payment_id` and resets `reconciliation_status =
  unreconciled`.
- Filters: `bank_account_id`, `reconciliation_status`, `status`, `category`; search
  `description`/`reference`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`.

## 3. Reconciliations

Module `reconciliations`. `Reconciliation` holds `bank_account_id`, `period_start`,
`period_end`, `opening_balance`, `closing_balance`, `status` and `completed_at`.

- `store` requires `period_end ≥ period_start` and a `closing_balance`; it starts in
  status `open` and, for every id in `transaction_ids[]`, creates a
  `reconciliation_items` row with `status = matched`.
- `complete` (422 if already `completed`) sets every linked bank transaction's
  `reconciliation_status = reconciled`, flips the reconciliation to `completed` with
  `completed_at = now()`, and emits the domain event `reconciliation.matched`.
- Filters: `bank_account_id`, `status`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`.

## 4. Transaction states

`reconciliation_status` walks `unreconciled → matched` (payment matched by amount) →
`reconciled` (folded into a completed reconciliation). `unmatch` returns a line to
`unreconciled`.

## 5. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='BankAccountTest|BankTransactionTest|ReconciliationTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `BankAccountTest` 4 + `BankTransactionTest` 4 + `ReconciliationTest` 3 — 11
passed (31 assertions); `PermissionCoverageTest` 5 passed. Full suite remains green.

## 6. Notes

- Banking is a core module: `bank-accounts`, `bank-transactions` and `reconciliations`
  are seeded in `RolesAndPermissionsSeeder::$modules`.
- `reconciliation.matched` is part of the webhook catalogue documented in
  `scripts/phase13-pilot.md`.
