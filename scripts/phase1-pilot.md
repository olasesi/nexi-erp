# Phase 1 Add-ons — Pilot Runbook

Six more **cross-domain tool add-ons** that follow the same
`config/addons.php` contract as the Phase 0 tools and the vertical solutions: one
entry drives the catalog listing, the per-company toggle, the permission seeding
and the route gating.

| Add-on id | Display | Permission modules | Prefix |
|---|---|---|---|
| `smart-reports` | Smart Reports | `smart-reports` | `/api/v1/smart-reports*` (+ `POST /smart-reports/{smart_report}/run`) |
| `ai-agent` | AI Agent | `ai-agent-threads`, `ai-agent-messages`, `ai-agent-prompts` | `/api/v1/ai-agent-threads*` (+ nested `/messages`), `/api/v1/ai-agent-messages*`, `/api/v1/ai-agent-prompts*` |
| `goals` | Goal | `goals`, `goal-key-results` | `/api/v1/goals*` (+ `progress`, `cancel`), `/api/v1/goal-key-results*` |
| `budget-planner` | Budget Planner | `budgets`, `budget-lines` | `/api/v1/budgets*` (+ `approve`, `close`), `/api/v1/budget-lines*` |
| `training` | Training | `trainings`, `training-types` | `/api/v1/trainings*` (+ `cancel`), `/api/v1/training-types*` |
| `youtube` | YouTube | `youtube-videos`, `youtube-playlists`, `youtube-channel`, `youtube-activity` | `/api/v1/youtube-videos*`, `/api/v1/youtube-playlists*`, `/api/v1/youtube-channel*`, `/api/v1/youtube-activity*` |

## 1. Add-on contract

Identical to `tools-pilot.md` — catalog (`GET /api/v1/addons`), toggle
(`PUT /api/v1/addons/{id} {enabled:bool}`), `addon:<id>` route gating returning
`403 {code:"addon_disabled", addon:"<id>"}`, and permission seeding from the
entry's `module` + `actions` / `modules` list. New `EnsurePermitted::ACTION_MAP`
suffixes this phase relies on: `run` → `view`, `progress` → `update` (alongside
the existing `approve`/`close`/`cancel` → `update`).

## 2. Invariants per add-on

- **Smart Reports** — a report is a saved definition (`source`, `columns`,
  `filters`, `group_by`, `aggregate`, `aggregate_field`); `run` executes it through
  `App\Services\SmartReportRunner`, which whitelists every identifier against the
  real table columns, caps output at `MAX_ROWS = 500` and only offers filter / group
  / aggregate operations, so a definition can never inject arbitrary SQL. Supported
  sources: `leads`, `deals`, `invoices`, `projects`, `tickets`, `expenses`,
  `contacts`, `products`.
- **AI Agent** — threads carry the transcript (`ai_agent_messages`) and reusable
  `ai_agent_prompts`. `POST /ai-agent-threads/{thread}/messages` stores the user
  message, asks the configured driver and stores the assistant reply, returning both
  as `201`; the thread's `last_message_at` is stamped. The driver is resolved from
  `config/ai.php` and defaults to the deterministic **local stub** — the add-on never
  requires a remote provider to be configured.
- **Goal** — objectives / OKRs / KPIs with `goal_key_results`. `progress` recomputes
  `progress = clamp(0, 100, round(current / target * 100))`, moving the goal to
  `completed` at 100; `cancel` only accepts a draft or active goal.
- **Budget Planner** — a budget holds income/expense `budget_lines`; every line
  mutation calls `Budget::recomputeTotals()`, so `total_planned`/`total_actual` always
  reflect live, non-deleted lines. Lifecycle `draft → active → closed` via
  `approve` (draft only) and `close` (active only).
- **Training** — trainings belong to a `training_type` and carry a lifecycle
  (`scheduled` default); list filters by `training_type_id` and `branch`, search spans
  `title`/`trainer`. `cancel` is refused once a training is cancelled or completed.
- **YouTube** — a settings-gated module: the channel row carries `is_connected`, so
  the whole surface stays dormant ("Configuration Required") until the channel is
  connected. Playlists, videos (attached to a playlist) and an `youtube_activity`
  feed round out the add-on.

## 3. Local verification

```powershell
# api/
php artisan migrate:fresh --seed        # tables + demo data + add-on permissions
php vendor\bin\pint --test
php -d memory_limit=2G vendor\bin\phpstan analyse --no-progress
php artisan test --filter='SmartReportsTest|AiAgentTest|GoalsTest|BudgetPlannerTest|TrainingTest|YoutubeTest'
```

## 3. End-to-end smoke test (API only)

```powershell
$base = "http://localhost:8000/api/v1"
$t = (Invoke-RestMethod "$base/login" -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t" }

# Every add-on still reports its effective toggle state
(Invoke-RestMethod "$base/addons" -Headers $h).data | Where-Object { $_.id -in 'smart-reports','ai-agent','goals','budget-planner','training','youtube' }

# Smart Report: save a definition and run it
$r = (Invoke-RestMethod "$base/smart-reports" -Method Post -Headers $h -ContentType application/json `
       -Body (@{name="Open deals";source="deals";columns=@('id','status','value')} | ConvertTo-Json)).data
(Invoke-RestMethod "$base/smart-reports/$($r.id)/run" -Method Post -Headers $h).data

# AI Agent: open a thread and send a message (local stub reply)
$t = (Invoke-RestMethod "$base/ai-agent-threads" -Method Post -Headers $h -ContentType application/json `
       -Body (@{title="Hello"} | ConvertTo-Json)).data
(Invoke-RestMethod "$base/ai-agent-threads/$($t.id)/messages" -Method Post -Headers $h -ContentType application/json `
       -Body (@{body="Summarise this week"} | ConvertTo-Json)).data   # [user, assistant]

# Budget Planner lifecycle
$b = (Invoke-RestMethod "$base/budgets" -Method Post -Headers $h -ContentType application/json `
       -Body (@{name="Q1";period_type="quarterly";start_date="2026-01-01";end_date="2026-03-31"} | ConvertTo-Json)).data
(Invoke-RestMethod "$base/budgets/$($b.id)/approve" -Method Post -Headers $h).data.status   # active

# Add-on guard
Invoke-RestMethod "$base/addons/youtube" -Method Put -Headers $h -ContentType application/json `
  -Body (@{enabled=$false} | ConvertTo-Json) | Out-Null
try { Invoke-RestMethod "$base/youtube-videos" -Headers $h } catch { $_.ErrorDetails.Message }   # addon_disabled
```

The API reference is generated with Scribe: `php artisan scribe:generate` (serve the
`laravel`-type docs at `/docs`). Every mutation is captured on `GET /api/v1/audit-logs`
and emitted to subscribed webhook endpoints.
