# Phase 2 Add-ons — HRM Suite — Pilot Runbook

Eleven **HRM add-ons** that follow the same `config/addons.php` contract as the
Phase 0 tools and the Phase 1 add-ons: one entry drives the catalog listing, the
per-company toggle, the permission seeding and the route gating. They cover the
employee lifecycle — performance, hiring, scheduling, time, commission and
compliance.

| Add-on id | Display | Permission modules | Prefix |
|---|---|---|---|
| `performance` | Performance | `performance-reviews` | `/api/v1/performance-reviews*` (+ `approve`, `reopen`) |
| `performance-indicator` | Performance Indicator | `performance-indicators` | `/api/v1/performance-indicators*` |
| `recruitment` | Recruitment | `job-postings`, `job-candidates`, `job-applications` | `/api/v1/job-postings*`, `/api/v1/job-candidates*`, `/api/v1/job-applications*` (+ `hire`) |
| `job-search` | Job Search | `job-openings` | `/api/v1/job-openings*` |
| `work-permit` | Work Permit | `work-permits` | `/api/v1/work-permits*` (+ `renew`) |
| `rotas` | Rotas | `rotas`, `rota-entries` | `/api/v1/rotas*` (+ `publish`), `/api/v1/rota-entries*` |
| `commission` | Commission | `commissions` | `/api/v1/commissions*` (+ `approve`, `mark-paid`) |
| `time-tracker` | Time Tracker | `time-entries` | `/api/v1/time-entries*` (+ `start`, `stop`) |
| `timesheet` | Timesheet | `timesheets`, `timesheet-entries` | `/api/v1/timesheets*` (+ `submit`, `approve`, `reject`), `/api/v1/timesheet-entries*` |
| `team-workload` | Team Workload | `team-workload` | `GET /api/v1/team-workload` (+ `/summary`) |
| `digital-certificate` | Digital Certificate | `digital-certificates` | `/api/v1/digital-certificates*` (+ `generate`, `revoke`) |

## 1. Add-on contract

Identical to `tools-pilot.md` and `phase1-pilot.md` — catalog
(`GET /api/v1/addons`), toggle (`PUT /api/v1/addons/{id} {enabled:bool}`),
`addon:<id>` route gating returning `403 {code:"addon_disabled", addon:"<id>"}`,
and permission seeding from the entry's `module` + `actions` / `modules` list.
New `EnsurePermitted::ACTION_MAP` suffixes this phase relies on: `renew`,
`mark-paid`, `start`, `stop`, `revoke` → `update`, alongside `hire` → `update`
and `generate` → `create` (and the existing `submit` → `create`, `summary` →
`view-any`, `approve`/`reject` → `update`).

Every HRM model uses the shared concerns (`CompanyScoped`, `DispatchesWebhooks`,
`HasFactory`, `RecordsActivity`, `SoftDeletes`) and is seeded with a factory +
feature test. Controllers extend `BaseController`; those that expose
filter/search fields **override `index()`** to call `applyFilters(...)` because
`BaseController::index()` only applies filters when the model defines
`isFilterable` (none do).

## 2. Invariants per add-on

- **Performance** — `performance_reviews` store a JSON `scores` map; the model
  summarises it into `overall_score` (mean of numeric ratings, rounded to 2dp,
  non-numeric entries ignored). Lifecycle `draft → submitted → approved`, where
  `approve` accepts a draft or submitted review and `reopen` returns an approved
  review to draft.
- **Performance Indicator** — catalog of weighted indicators (`name`, `category`,
  `weight`, `target_value`) used to score reviews; filterable by category and
  searchable by name.
- **Recruitment** — `job_postings` (status open/closed/paused), `job_candidates`
  and `job_applications` (`applied` → `shortlisted`/`interview`/`offer` /`hired` /
  `rejected`) idempotent pipeline; `hire` promotes a candidate to an employee.
- **Job Search** — candidate-facing, **read-only** listing of open job postings
  (`job-openings`) with an apply surface collecting `job_applications`. Seeded
  with `view-any`/`view` only.
- **Work Permit** — permits carry issuer, `issued_date`, `expiry_date` and a
  status (`active`/`expiring`/`expired`/`revoked`); `renew` requires a future
  `expiry_date`, stamps `issued_date`, resets status to `active`, and refuses a
  revoked permit.
- **Rotas** — a `rota` (draft/published) owns `rota_entries` (shift date, times,
  type, employee). `publish` moves draft → published and refuses a second
  publish; entries are filterable by rota/employee/date.
- **Commission** — `amount` is derived as `round(sale_amount * rate / 100, 2)`
  when not supplied; lifecycle `pending → approved → paid` via `approve` and
  `mark-paid`, each refusing an out-of-order transition.
- **Time Tracker** — a `time_entry` may be running (`started_at` set,
  `ended_at` null). `start` refuses an already-running timer; `stop` computes
  `duration_minutes` and refuses a stopped one. Billable flag + hourly rate.
- **Timesheet** — a period `timesheet` (draft/submitted/approved/rejected) owns
  line `timesheet_entries`; saving an entry calls `recalculateTotal()` to update
  `total_hours`. `submit` (draft/rejected → submitted), `approve` (submitted →
  approved) and `reject` (submitted → rejected + required reason) enforce the
  workflow.
- **Team Workload** — read-only aggregate (no table): per-employee rows of open
  tasks, open tickets and pending/approved timesheets plus `total_load`, and a
  `summary()` with company-wide totals and `average_load`. Queries are
  company-scoped via explicit `where('company_id', …)`.
- **Digital Certificate** — a certificate is drafted then `generate`d (assigns a
  `serial` UUID and `certificate_number`, stamps `issued_date`, status `issued`);
  `revoke` (issued → revoked) stores a required reason and `revoked_at`.

## 3. Local verification

```powershell
# api/
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='PerformanceReviewTest|PerformanceIndicatorTest|RecruitmentTest|JobSearchTest|WorkPermitTest|RotasTest|CommissionTest|TimeEntryTest|TimesheetTest|TeamWorkloadTest|DigitalCertificateTest'
```

## 4. API smoke test

```powershell
$t = (Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t" }

# Performance review → approve
$r = (Invoke-RestMethod http://localhost:8000/api/v1/performance-reviews -Method Post -Headers $h -ContentType application/json `
       -Body (@{employee_id=1;scores=@{quality=4;teamwork=5}} | ConvertTo-Json)).data
(Invoke-RestMethod "http://localhost:8000/api/v1/performance-reviews/$($r.id)/approve" -Method Post -Headers $h).data.status  # approved

# Commission amount derivation
(Invoke-RestMethod http://localhost:8000/api/v1/commissions -Method Post -Headers $h -ContentType application/json `
  -Body (@{title="Deal";sale_amount=10000;rate=5} | ConvertTo-Json)).data.amount  # 500

# Time tracker start/stop
$e = (Invoke-RestMethod http://localhost:8000/api/v1/time-entries -Method Post -Headers $h -ContentType application/json `
       -Body (@{task="Spec"} | ConvertTo-Json)).data
Invoke-RestMethod "http://localhost:8000/api/v1/time-entries/$($e.id)/stop" -Method Post -Headers $h | Out-Null

# Read-only team workload
Invoke-RestMethod http://localhost:8000/api/v1/team-workload -Headers $h
Invoke-RestMethod http://localhost:8000/api/v1/team-workload/summary -Headers $h

# Add-on guard
Invoke-RestMethod http://localhost:8000/api/v1/addons/commission -Method Put -Headers $h -ContentType application/json `
  -Body (@{enabled=$false} | ConvertTo-Json) | Out-Null
try { Invoke-RestMethod http://localhost:8000/api/v1/commissions -Headers $h } catch { $_.ErrorDetails.Message }  # addon_disabled
```

The API reference is generated with Scribe: `php artisan scribe:generate`. Ship
with the deploy/scale/monitor baseline from `notifications-pilot.md`.
