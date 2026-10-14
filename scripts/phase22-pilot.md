# Phase 22 — Accounting (Chart of Accounts & Journal Entries) — Pilot Runbook

This phase documents the core **double-entry accounting** surfaces:
`chart-of-accounts` and `journal-entries`. These are core staff-authenticated API
surfaces (no `addon:` middleware) and the target that `AccountingService` (Phase
19/20) writes into via `GeneralLedgerService`.

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET/POST/PUT/PATCH/DELETE /api/v1/chart-of-accounts*`, `POST /api/v1/chart-of-accounts/{id}/restore`
- `GET/POST/PUT/PATCH/DELETE /api/v1/journal-entries*`,
  `POST /api/v1/journal-entries/{id}/post`, `POST /api/v1/journal-entries/{id}/void`

## 1. Chart of accounts

Module `chart-of-accounts`. `ChartOfAccount` holds `company_id`, `code`, `name`,
`type` (`asset`/`liability`/`equity`/`revenue`/`expense`), `normal_balance`
(`debit`/`credit`), `parent_id`, `description`, `is_active` and `is_system`; the
`parent`/`children` relations form a tree.

- `store` validates `code` unique per company and a `normal_balance` matching the
  account `type`; `index` orders by `code`, filters by `type`/`is_active`,
  searches `code`/`name`, and returns an **unpaginated** list when `?all=1`.
- `GeneralLedgerService::DEFAULT_ACCOUNTS` (22 system codes: `1010` Cash, `1100`
  A/R, `1200` Inventory, `2010` A/P, `4010` Sales Revenue, `5010` COGS, …) is
  installed per company on demand via `ensureDefaultAccounts`.

Invariants: `CompanyScoped`, `SoftDeletes`.

## 2. Journal entries

Module `journal-entries`. `JournalEntry` holds `entry_number`
(`JE-YYYYMMDD-0001`), `description`, `entry_date`, `status`
(`draft`/`posted`/`void`), a `source_type`/`source_id` morph and `created_by` /
`posted_at`. `JournalEntryLine` holds `chart_of_account_id`, `description`,
`debit` and `credit`.

- `store` delegates to `GeneralLedgerService::post`, which installs default
  accounts, normalises each line (resolved by `chart_of_account_id` or
  `account_code`), and enforces: a line cannot be both debit and credit; a line
  must carry a value; each account must exist; **debits must equal credits**
  (±0.001); and there must be a positive amount. Unbalanced/unknown lines return
  **422**. `status` defaults to `posted` (with `posted_at`), otherwise `draft`.
- `update` only touches `draft` entries (posted returns 422): it rewrites the
  lines and re-checks the balance. `destroy` only deletes `draft` entries (posted
  entries must be voided) after removing lines.
- `post` (→ `update`) flips a `draft` entry to `posted` and stamps `posted_at`;
  `void` (→ `update`) sets a non-void entry to `void`. `index` filters by
  `status`/`entry_date` and searches `entry_number`/`description`.

Invariants: `CompanyScoped`.

## 3. Ledger integration

`GeneralLedgerService::post` is the single write path used by
`AccountingService::postInvoice`/`postPayment` (Phases 19–20): invoices/payments
create entries whose `source_type`/`source_id` point at the originating model.
`voidForSource($source)` marks every **posted** entry for that source as `void` —
this is how cancelling an invoice or deleting a payment reverses its ledger.

## 4. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='ChartOfAccountTest|JournalEntryTest|GeneralLedgerServiceTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `ChartOfAccountTest` 5 + `JournalEntryTest` 6 +
`GeneralLedgerServiceTest` 3 — 14 passed (31 assertions). `PermissionCoverageTest`
5 passed. Full suite remains green.

## 5. Notes

- `post`/`void` are in `EnsurePermitted::ACTION_MAP` (`'post' => 'update'`,
  `'void' => 'update'`).
- `JournalEntry` is intentionally **not** soft-deleted (a voided entry stays as an
  audit trail); `ChartOfAccount` is soft-deleted but neither model emits webhooks
  or activity-log entries, unlike the commerce documents.
- Entry numbers count existing company rows (`max(id) + 1`) at generation time;
  default accounts are looked up by `code` within the company scope.
