# Phase 24 — Contacts & Customer Groups (Core + Add-on) — Pilot Runbook

This phase documents the **Contacts** module (core) and **Customer Groups**
(`customer-group` add-on). Contacts are the shared party record used by sales,
purchases, payments and the customer portal.

Routes (`auth:api` + `staff` + `permitted`):
- Core: `POST /api/v1/contacts/import`, `GET /api/v1/contacts/export`,
  `GET/POST/PUT/PATCH/DELETE /api/v1/contacts*`, `POST /api/v1/contacts/{id}/restore`
- Add-on `customer-group`: `GET/POST/PUT/PATCH/DELETE /api/v1/customer-groups*`,
  `POST /api/v1/customer-groups/{id}/restore`

## 1. Contacts (core)

Module `contacts`. `Contact` holds `company_id`, `type`, `first_name`, `last_name`,
`email`, `phone`, `mobile`, `job_title`, `department`, `language`, `notes` and
`is_active`.

- `type` ∈ `customer`, `supplier`, `lead`, `both`; `first_name`/`last_name` are
  required. `addresses()` is a `MorphMany`, `user()` a `HasOne` (portal login), and
  `full_name` is an accessor.
- `index` eager-loads `addresses`, filters by `type`/`company_id`/`is_active`, and
  searches `first_name`/`last_name`/`email`/`phone`.
- CSV `import` (inline or uploaded, optional `dry_run`) uses `ContactImporter`,
  which normalises and validates each row (`first_name` required; valid `email`;
  `type` ∈ {customer, supplier, both}) and upserts keyed by **email** within the
  company, returning per-row `created`/`updated`/`error`. `export` streams
  `contacts.csv` (`id, first_name, last_name, email, phone, type, is_active`).

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`.

## 2. Customer groups (add-on `customer-group`)

Module `customer-groups`. `CustomerGroup` holds `company_id`, `name`,
`description`, `discount_rate`, `is_active` and `created_by`.

- `store` defaults `company_id`/`created_by` from the authenticated user,
  `discount_rate` to `0` and `is_active` to `true`; `discount_rate` is validated
  `0–100`. `index` orders by `name`, filters by `is_active`, and searches `name`.
- The add-on answers `403 {code:"addon_disabled"}` before the permission check when
  switched off; unpermissioned users get a `403`.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`.

## 3. Customer portal

A contact can be linked to a `user` (portal login). The customer-portal surfaces
(register, `me`, contact-scoped documents/sales) and the staff/customer role split
are documented in `scripts/auth-pilot.md`.

## 4. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='ContactTest|CustomerGroupTest'
php artisan test --filter='BulkImportExportTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `ContactTest` 8 + `CustomerGroupTest` 10 — 18 passed (57 assertions). The
shared `BulkImportExportTest` (10 passed, 46 assertions) covers the contact
import/export paths. `PermissionCoverageTest` 5 passed. Full suite remains green.

## 5. Notes

- `customer-group` is an add-on; `contacts` is core and part of the seeded `user`
  role's baseline permissions (view-any/view/create/update).
- Contacts are the foreign key target for invoices, sales/purchase orders and
  payments, so their `email` doubles as the CSV import key and the portal
  invitation address.
