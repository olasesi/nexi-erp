# Phase 20 — Payments (Core Surface) — Pilot Runbook

This phase documents **payments** (`payments`): money received (receipts) and money
paid out, their ledger postings and how they roll up into invoice paid/balance
state. Core staff-authenticated API (no `addon:` middleware).

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET/POST/PUT/PATCH/DELETE /api/v1/payments*`, `POST /api/v1/payments/{id}/restore`
- `POST /api/v1/payments/{id}/complete`

## 1. Payments

Module `payments`. `Payment` carries `payment_number`, `type`, `contact_id`,
`invoice_id`, `payment_date`, `amount`, `method`, `status`, `reference`, `notes`
and `bank_account_id`.

- `type` ∈ `receipt` (money in), `payment` (money out); `method` ∈ `cash`,
  `bank_transfer`, `credit_card`, `check`, `other`; `status` ∈ `pending`,
  `completed`, `failed`, `refunded`.
- `payment_number` is `REC-YYYYMMDD-00001` for receipts and `PAY-YYYYMMDD-00001`
  for payments; `status` defaults to `pending`.
- `store` runs `reconcileAccounting` inside a transaction (posts to the ledger +
  refreshes the linked invoice). `update` reconciles only when the resulting
  `status` is not `pending`.
- `complete` flips the payment to `completed`, reconciles, and emits
  `payment.completed`.
- `destroy` voids the ledger entry, deletes the payment and re-derives the
  invoice's paid amount/status. `index` filters by `type`/`status`/`invoice_id`/
  `contact_id` and searches `payment_number`/`reference`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`.

## 2. Ledger + invoice reconciliation

`AccountingService::postPayment` first voids any prior entry for the payment, then
(only for `completed` payments with a positive amount) posts a balanced pair:

| `type` | Debit | Credit |
| --- | --- | --- |
| `receipt` | `1010` Cash | `1100` Accounts Receivable (if `invoice_id`) else `4030` Other Income |
| `payment` | `2010` Accounts Payable (if `invoice_id`) else `5700` Expense | `1010` Cash |

`AccountingService::refreshInvoiceFromPayments` recomputes `paid_amount`
(Σ completed payments, capped at `total`) and `balance_due = total − paid`
(floored at 0), then sets invoice `status`: `paid` when the balance clears
(`≤ 0.01` and `total > 0`), `partial` when partially paid, otherwise back to `sent`
from `paid`/`partial`. This is what makes the Phase 19 "paid amount and status from
completed payments" invariant hold.

## 3. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter=PaymentTest
php artisan test --filter=PermissionCoverageTest
```

Expected: `PaymentTest` 6 passed (17 assertions). `PermissionCoverageTest` 5
passed. Full suite remains green.

## 4. Notes

- `complete` is in `EnsurePermitted::ACTION_MAP` (`'complete' => 'update'`).
- Pending/failed/refunded (or non-positive) payments post nothing; the ledger is
  always voided first so a status change cannot leave a stale entry.
- The public `invoices/pay/{token}` and customer-portal `payments` endpoints are
  separate public/customer surfaces; the public payment path drives the same
  `postPayment`/`refreshInvoiceFromPayments` logic.
