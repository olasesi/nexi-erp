# Phase 9 — CRM (Leads, Deals, CRM Stages, Deal Activities) — Pilot Runbook

CRM core surfaces are already implemented: leads, deals, pipeline stages, and deal
activities. This phase verifies and documents them. CRM is not gated as an add-on
in routes; it's part of the core staff-authenticated API. The `deal-activity`
add-on is a separate toggle that gates `/api/v1/deal-activities*`.

| Surface | Modules | Routes |
|---|---|---|
| CRM Pipeline | `leads`, `deals`, `crm-stages` | `GET/POST /api/v1/leads*`, `GET/POST/PUT/PATCH/DELETE/restore /api/v1/leads/{id}`, `POST /api/v1/leads/{id}/convert`; `GET/POST/PUT/PATCH/DELETE/restore /api/v1/deals*`; `GET/POST/PUT/PATCH/DELETE/restore /api/v1/crm-stages*`; `GET /api/v1/dashboard/crm-summary` |
| Deal Activity (add-on) | `deal-activities` | `GET/POST/PUT/PATCH/DELETE/restore /api/v1/deal-activities*`, `POST /api/v1/deal-activities/{id}/complete` (addon: `deal-activity`) |

## 1. Invariants

- **Leads** (`crm_leads`): CompanyScoped, SoftDeletes, webhooks, activity. Status `new/contacted/qualified/converted/lost`; sources `referral/website/social/email/call/event/other`. `convert` creates/reuses a Contact, creates a Deal on the first pipeline stage (ordered by `position,id`), marks lead `converted` (422 if already converted or lost). Active scope = not converted/lost.
- **CRM Stages**: Pipeline ordered by `position`, per-company.
- **Deals**: CompanyScoped. Status `open/won/lost`; belong to stage/contact/lead/assigned user; `created_by` auto-set.
- **Deal Activities**: CompanyScoped, SoftDeletes, gated by addon `deal-activity`. Status `pending/completed`; `complete()` sets status+`completed_at`. Company-scoped and scoped to deal as appropriate.

## 2. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter=LeadTest
php artisan test --filter=DealTest
php artisan test --filter=ContactTest
php artisan test --filter=CrmStageTest
php artisan test --filter=DealActivityTest
php artisan test --filter=PermissionCoverageTest
```

## 3. API smoke test

```powershell
$t=(Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json -Body (@{email="admin@nexi-corp.com";password="password"}|ConvertTo-Json)).access_token
$h=@{Authorization="Bearer $t"}

# Create lead
$l=(Invoke-RestMethod http://localhost:8000/api/v1/leads -Method Post -Headers $h -ContentType application/json -Body (@{name="Jane Doe";email="jane@example.com";value=5000}|ConvertTo-Json)).data

# Convert lead (needs at least one crm stage)
# stage auto-created in seed? If not, create
try { (Invoke-RestMethod http://localhost:8000/api/v1/leads/$($l.id)/convert -Method Post -Headers $h) } catch { $_.ErrorDetails.Message }
```

## 4. Notes

- Core CRM routes (leads/deals/crm-stages) are behind `auth:api`, `staff`, `permitted` middleware, not an add-on. `deal-activities` are add-on gated (`addon:deal-activity`).
- Dashboard CRM summary at `/api/v1/dashboard/crm-summary` (core).
