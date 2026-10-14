# Phase 25 — Audit Logs (Core Surface) — Pilot Runbook

This phase documents the read-only **audit trail** (`audit-logs`): the immutable
record of create/update/delete events emitted by activity-tracked models.

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET /api/v1/audit-logs`, `GET /api/v1/audit-logs/{id}`

## 1. Audit records

Module `audit-logs` (read-only). `AuditLog` holds `company_id`, `user_id`, `event`
(`created`/`updated`/`deleted`), `auditable_type`/`auditable_id` (morph), and
`old_values`/`new_values` (array casts), plus request context (`ip_address`,
`user_agent`).

- `index` returns entries **latest first**, filtering by `event` /
  `auditable_type` / `user_id`, and by `company_id` via `applyFilters`. `show`
  returns a single entry.

Invariants: `CompanyScoped`. The table is append-only — there is no store/update/
delete endpoint.

## 2. How entries are written

Models that use the `RecordsActivity` concern hook their `created` / `updated` /
`deleted` events and call `AuditLogService::record(...)`. The service:

- attributes the change to the authenticated API user (`user_id`) and the model's
  `company_id`;
- stores the resolved `getMorphClass()` (`auditable_type`) and primary key;
- **scrubs sensitive attributes** (`password`, `remember_token`) from both the old
  and new value snapshots before persisting;
- captures `ip_address` and a user-agent truncated to 500 characters.

`RecordsActivity` is used by the commerce/CRM models (contacts, products, orders,
invoices, payments, expenses, projects, …) so edits are traceable without any extra
API call.

## 3. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter=AuditLogTest
php artisan test --filter=PermissionCoverageTest
```

Expected: `AuditLogTest` 6 passed (20 assertions). `PermissionCoverageTest` 5
passed. Full suite remains green.

## 4. Notes

- The module is seeded with read-only actions (`view-any` / `view`); the trail is
  never writable through the API.
- Because `user_id` may be null for system/console changes, entries are keyed to
  the company and are only visible to users with the `audit-logs` permission.
