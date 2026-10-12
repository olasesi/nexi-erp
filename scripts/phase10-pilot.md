# Phase 10 — Projects & HRM (Core Surfaces) — Pilot Runbook

This phase documents the existing Projects and HRM surfaces. Both are part of the
core staff-authenticated API (not gated by `addon:` middleware); they use the
standard permission model and CompanyScoped concerns.

## 1. Projects

Modules: `projects`, `project-tasks`, `project-milestones`. Routes (core):
- `GET /api/v1/projects/summary` (dashboard feed)
- `GET/POST /api/v1/projects*`, `GET/PUT/PATCH/DELETE /api/v1/projects/{id}`, `POST /api/v1/projects/{id}/restore`
- `GET/POST/PUT/PATCH/DELETE /api/v1/project-tasks*`, `POST /api/v1/project-tasks/{id}/restore`
- `GET/POST/PUT/PATCH/DELETE /api/v1/project-milestones*`, `POST /api/v1/project-milestones/{id}/restore`

Invariants: CompanyScoped, SoftDeletes, webhooks, activity; statuses/lifecycle as defined in models; summary endpoints feed dashboard.

## 2. HRM

Modules: `hrm-departments`, `hrm-designations`, `hrm-employees`, `hrm-attendance`, `hrm-leaves`. Routes (core):
- `GET /api/v1/hrm-employees/summary`
- `GET/POST/PUT/PATCH/DELETE/restore` for departments, designations, employees
- `GET/POST/PUT/PATCH` for attendance (as implemented)
- `GET/POST/PUT/PATCH/DELETE/restore` for leaves; `POST /api/v1/hrm-leaves/{id}/approve`, `POST /api/v1/hrm-leaves/{id}/reject`

Invariants: CompanyScoped, SoftDeletes where applicable, webhooks, activity; leave approval lifecycle enforced.

## 3. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter=Project
php artisan test --filter=Hrm
php artisan test --filter=PermissionCoverageTest
```

## 4. Notes

- Projects and HRM routes are in the main authenticated group (`auth:api`, `staff`, `permitted`); no `addon:` gate.
- Existing tests cover CRUD, filters/search, restore, summaries and permission gating.
- Full suite remains green.
