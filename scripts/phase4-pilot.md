# Phase 4 Add-ons — Collaboration — Pilot Runbook

Six **collaboration add-ons** that round out the internal-communication surface:
company announcements, personal todos, a categorised knowledge base, quick notes,
employee awards and versioned company policies. They follow the same
`config/addons.php` contract — one entry drives the catalog listing, the
per-company toggle, the permission seeding and the route gating.

| Add-on id | Display | Permission modules | Prefix |
|---|---|---|---|
| `announcement` | Announcement | `announcements` | `/api/v1/announcements*` (+ `publish`, `archive`) |
| `todo` | Todo | `todos` | `/api/v1/todos*` (+ `complete`) |
| `knowledge-base` | Knowledge Base | `kb-categories`, `kb-articles` | `/api/v1/kb-categories*`, `/api/v1/kb-articles*` (+ `publish`) |
| `note` | Note | `notes` | `/api/v1/notes*` |
| `award` | Award | `awards` | `/api/v1/awards*` |
| `company-policy` | Company Policy | `company-policies` | `/api/v1/company-policies*` (+ `publish`, `archive`) |

## 1. Add-on contract

Identical to `tools-pilot.md`, `phase1-pilot.md`, `phase2-pilot.md` and
`phase3-pilot.md` — catalog (`GET /api/v1/addons`), toggle
(`PUT /api/v1/addons/{id} {enabled:bool}`), `addon:<id>` route gating returning
`403 {code:"addon_disabled", addon:"<id>"}`, and permission seeding from the
entry's `module` + `actions` / `modules` list. This phase needs no new
`EnsurePermitted::ACTION_MAP` entries: `publish`/`archive`/`complete` already map
to `update`.

## 2. Invariants per add-on

- **Announcement** — a company notice with a `draft → published → archived`
  lifecycle; `publish` stamps `published_at` and `archive` moves a published
  notice to archived, each refusing an out-of-order transition.
- **Todo** — a personal task (`user_id` optional, defaults to the acting user)
  with `priority`, `status` and `due_date`; `complete` moves it to completed and
  stamps `completed_at`, refreshing the ordering.
- **Knowledge Base** — self-referential categories (`kb-categories`) holding
  articles (`kb-articles`). An article is `draft → published`; `publish` only runs
  on a draft and stamps `published_at` (a second publish is refused with 422).
  Fetching an article increments its `views` counter.
- **Note** — a quick note with an `is_pinned` flag (defaults to false) and an
  optional `color`; the list is ordered pinned-first.
- **Award** — recognises an employee (`employee_id` → `hrm_employees`) with an
  `award_type`, `title`, optional `gift` and `awarded_date`; the list is ordered by
  `awarded_date` desc.
- **Company Policy** — a versioned policy (`version`, `effective_date`) with a
  `draft → published → archived` lifecycle: `publish` only runs on a draft and
  stamps `published_at`, `archive` only runs on a published policy; both refuse
  invalid transitions with 422.

## 3. Local verification

```powershell
# api/
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='AnnouncementTest|TodoTest|KnowledgeBaseTest|NoteTest|AwardTest|CompanyPolicyTest'
```

## 4. API smoke test

```powershell
$t = (Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t" }

# Announcement lifecycle
$a = (Invoke-RestMethod http://localhost:8000/api/v1/announcements -Method Post -Headers $h -ContentType application/json `
       -Body (@{title="Office closed";body="Holiday"} | ConvertTo-Json)).data
(Invoke-RestMethod "http://localhost:8000/api/v1/announcements/$($a.id)/publish" -Method Post -Headers $h).data.status  # published

# Knowledge base: category → draft article → publish (and views increments on show)
$c = (Invoke-RestMethod http://localhost:8000/api/v1/kb-categories -Method Post -Headers $h -ContentType application/json `
       -Body (@{name="Getting Started"} | ConvertTo-Json)).data
$art = (Invoke-RestMethod http://localhost:8000/api/v1/kb-articles -Method Post -Headers $h -ContentType application/json `
       -Body (@{kb_category_id=$c.id;title="Reset your password";body="Click the reset link."} | ConvertTo-Json)).data
(Invoke-RestMethod "http://localhost:8000/api/v1/kb-articles/$($art.id)/publish" -Method Post -Headers $h).data.status  # published
(Invoke-RestMethod "http://localhost:8000/api/v1/kb-articles/$($art.id)" -Headers $h).data.views  # 1

# Company policy lifecycle
$p = (Invoke-RestMethod http://localhost:8000/api/v1/company-policies -Method Post -Headers $h -ContentType application/json `
       -Body (@{title="Remote Work Policy";version="1.0"} | ConvertTo-Json)).data
Invoke-RestMethod "http://localhost:8000/api/v1/company-policies/$($p.id)/publish" -Method Post -Headers $h | Out-Null
(Invoke-RestMethod "http://localhost:8000/api/v1/company-policies/$($p.id)/archive" -Method Post -Headers $h).data.status  # archived

# Add-on guard
Invoke-RestMethod http://localhost:8000/api/v1/addons/knowledge-base -Method Put -Headers $h -ContentType application/json `
  -Body (@{enabled=$false} | ConvertTo-Json) | Out-Null
try { Invoke-RestMethod http://localhost:8000/api/v1/kb-articles -Headers $h } catch { $_.ErrorDetails.Message }  # addon_disabled
```

The API reference is generated with Scribe: `php artisan scribe:generate` (serve the
`laravel`-type docs at `/docs`). Every mutation is captured on `GET /api/v1/audit-logs`
and emitted to subscribed webhook endpoints.
