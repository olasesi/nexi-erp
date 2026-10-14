# Phase 29 — Suggestion Box (Add-on `suggestion-box`) — Pilot Runbook

This phase documents the **Suggestion Box** add-on (`suggestion-box`): staff submit
ideas/improvement requests, which are then reviewed.

Routes (add-on `suggestion-box`, then the `suggestions.*` permissions):
- `GET/POST/PUT/PATCH/DELETE /api/v1/suggestions*`,
  `POST /api/v1/suggestions/{id}/restore`

## 1. Contract

Add-on id `suggestion-box`, module `suggestions`, category "Communication and
Customization", permissions `view-any`/`view`/`create`/`update`/`delete`. A
switched-off add-on answers `403 {code:"addon_disabled"}` before the permission
check.

## 2. Suggestions

`Suggestion` holds `company_id`, `title`, `body`, `category`, `status`,
`submitted_by`, `reviewed_by` and `reviewed_at`.

- `status` ∈ `open`, `in_review`, `accepted`, `rejected` (`Suggestion::STATUSES`);
  `category` ∈ `idea`, `improvement`, `issue`, `other` (`Suggestion::CATEGORIES`).
- `store` defaults `company_id`/`submitted_by` from the authenticated user and
  `status` to `open`. `stampReview` records `reviewed_by`/`reviewed_at` only when a
  request moves the status **away** from its current value — so the box keeps an
  auditable review trail without a workflow engine (no stamp when the status is
  unchanged).
- `index` filters by `status`/`category`, searches `title`/`body`, and returns
  latest-first. `destroy`/`restore` soft-delete and recover.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`.

## 3. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter=SuggestionTest
php artisan test --filter=PermissionCoverageTest
```

Expected: `SuggestionTest` 9 passed (32 assertions). `PermissionCoverageTest` 5
passed. Full suite remains green.

## 4. Notes

- Suggestions are company-scoped to the acting user; submitting requires only the
  `suggestions.create` permission, so ordinary staff can contribute.
- The review stamp is intentionally lightweight: any status change records the
  reviewer and time, giving an auditable trail without a workflow engine.
