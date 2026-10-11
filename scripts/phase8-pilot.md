# Phase 8 — Document Manager (Media/File Manager slice) — Pilot Runbook

The `document-manager` add-on was previously implemented. This phase verifies
and documents its surface (folders + documents with sharing and restore). No new
routes/controllers were added beyond the existing ones.

| Add-on id | Display | Modules | Routes |
|---|---|---|---|
| `document-manager` | Document Manager | `document-folders`, `documents` | `/api/v1/document-folders*` (index/show/store/update/destroy/restore), `/api/v1/documents*` (index/show/store/update/destroy/restore, `POST /documents/{id}/share`) |

## 1. Contract & gating

- Catalog and per-company toggle via `config/addons.php` entry `document-manager`.
- `addon:document-manager` gates folder and document routes.
- Permissions seeded from `document-folders` and `documents` actions: `view-any|view|create|update|delete`.
- `share` maps to `update` (per existing rules); `restore` maps to `delete` contextually (SoftDeletes).

## 2. Models & invariants

- `DocumentFolder`: CompanyScoped, DispatchesWebhooks, RecordsActivity, SoftDeletes. `parent_id` nullable; `destroy` blocks if has `children` or `documents`.
- `Document`: CompanyScoped, DispatchesWebhooks, RecordsActivity, SoftDeletes. Types `file|folder|link`. `version` auto-increments when `content` or `url` changes (PATCH/PUT). `shared_with` (array of user ids), `is_public` (bool), `size`, `mime_type`, `created_by`. `share` merges unique user ids after validation (`user_ids.*` exists in users table).
- Both scoped to acting user's company; list endpoints filterable/searchable.

## 3. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter=DocumentManagerTest
php artisan test --filter=PermissionCoverageTest
php artisan test  # full suite (detached in prior runs)
```

## 4. API smoke test

```powershell
$t=(Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json -Body (@{email="admin@nexi-corp.com";password="password"}|ConvertTo-Json)).access_token
$h=@{Authorization="Bearer $t"}

# create folder
$f=(Invoke-RestMethod http://localhost:8000/api/v1/document-folders -Method Post -Headers $h -ContentType application/json -Body (@{name="Contracts"}|ConvertTo-Json)).data

# create document in folder
$d=(Invoke-RestMethod http://localhost:8000/api/v1/documents -Method Post -Headers $h -ContentType application/json -Body (@{name="NDA.pdf";type="file";folder_id=$f.id}|ConvertTo-Json)).data
$d.type # file, version 1

# update content -> version bumps
(Invoke-RestMethod http://localhost:8000/api/v1/documents/$($d.id) -Method Put -Headers $h -ContentType application/json -Body (@{content="draft"}|ConvertTo-Json)).data.version

# share
(Invoke-RestMethod http://localhost:8000/api/v1/documents/$($d.id)/share -Method Post -Headers $h -ContentType application/json -Body (@{user_ids=@(1)}|ConvertTo-Json)).data.shared_with

# restore
Invoke-RestMethod http://localhost:8000/api/v1/documents/$($d.id) -Method Delete -Headers $h | Out-Null
(Invoke-RestMethod http://localhost:8000/api/v1/documents/$($d.id)/restore -Method Post -Headers $h).data.id

# folder delete blocked if has docs; delete doc first then folder
Invoke-RestMethod http://localhost:8000/api/v1/documents/$($d.id) -Method Delete -Headers $h | Out-Null
(Invoke-RestMethod http://localhost:8000/api/v1/document-folders/$($f.id) -Method Delete -Headers $h).statusCode  # 204
```

## 5. Cross-cutting

- Webhooks + activity logged on mutations (via DispatchesWebhooks, RecordsActivity).
- Company-scoped; add-on gating enforced by `addon:document-manager` middleware.
- PermissionCoverageTest enforces seeded permissions for all gated routes.
