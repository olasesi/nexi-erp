# Phase 31 — Notice Board (Add-on `notice-board`) — Pilot Runbook

This phase documents the **Notice Board** add-on (`notice-board`): company-wide
announcements with a priority and a publish/expiry window.

Routes (add-on `notice-board`, then the `notices.*` permissions):
- `GET/POST/PUT/PATCH/DELETE /api/v1/notices*`,
  `POST /api/v1/notices/{id}/restore`

## 1. Contract

Add-on id `notice-board`, module `notices`, category "Communication and
Customization", permissions `view-any`/`view`/`create`/`update`/`delete`,
`enabled_by_default` true. A switched-off add-on answers
`403 {code:"addon_disabled", addon:"notice-board"}` before the permission check.

## 2. Visibility window

`Notice` is company-scoped and carries `title`, `body`, `priority`,
`published_at`, `expires_at` and `created_by`.

- **Default list is the live board**: `index` applies `scopeActive()` — published
  (`published_at` null *or* `<=` today) **and** not yet expired (`expires_at` null
  or `>=` today). `?status=all` switches to the management view (drafts and expired
  notices included), newest by `published_at` then `id`.
- **`store`** stamps the acting user's `company_id`/`created_by` and defaults
  `published_at` to **today** so an undated notice goes live immediately rather
  than never.
- `priority` is one of `low`, `normal`, `high`, `urgent`; `published_at`/
  `expires_at` are optional dates. Filters: `priority`; search: `title`/`body`.
- Delete is soft; `POST /notices/{id}/restore` brings one back.

Invariants: `CompanyScoped`, `SoftDeletes`, `DispatchesWebhooks`, `RecordsActivity`.

## 3. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter=NoticeTest
php artisan test --filter=PermissionCoverageTest
```

Expected: `NoticeTest` 11 passed (40 assertions). `PermissionCoverageTest` 5 passed. Full suite
remains green.

## 4. Notes

- A notice with no `expires_at` stays active indefinitely; `scopeActive` treats a
  null publish/expiry as "always live", so an undated notice is visible the moment
  it is created.
- The management view is opt-in via `?status=all`; the default board feed never
  leaks drafts or expired notices.
