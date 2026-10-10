# Cross-Domain Tools — Pilot Runbook

Six **cross-domain tool add-ons** any company can switch on, independent of industry.
They follow the same add-on contract as the vertical solutions
(`coworking-space`, `laundry-dry-cleaning`, `gym-management`, `rental`): one
`config/addons.php` entry drives the catalog listing, the per-company toggle, the
permission seeding and the route gating.

| Add-on id | Display | Permission modules | Prefix |
|---|---|---|---|
| `form-builder` | Form Builder | `forms`, `form-fields`, `form-submissions` | `/api/v1/forms*`, `/api/v1/form-fields*`, `/api/v1/form-submissions*` |
| `messenger` | Messenger | `conversations`, `messages` | `/api/v1/conversations*`, `/api/v1/messages*` |
| `document-manager` | Document Manager | `document-folders`, `documents` | `/api/v1/document-folders*`, `/api/v1/documents*` |
| `support-tickets` | Support Tickets | `tickets`, `ticket-replies` | `/api/v1/tickets*`, `/api/v1/ticket-replies*` |
| `calendar-events` | Calendar & Events | `events`, `event-attendees` | `/api/v1/events*`, `/api/v1/event-attendees*` |
| `asset-management` | Asset Management | `asset-categories`, `assets`, `asset-assignments` | `/api/v1/asset-categories*`, `/api/v1/assets*`, `/api/v1/asset-assignments*` |

## 1. Add-on contract

- **Catalog** — `GET /api/v1/addons` returns every entry with its effective `enabled`
  state for the caller's company. `PUT /api/v1/addons/{id}` `{enabled:bool}` toggles
  it (rides `addons.update`).
- **Gating** — each route group sits behind `Route::middleware('addon:<id>')`. A
  switched-off add-on answers `403` with `{code:"addon_disabled", addon:"<id>"}`
  **before** the `permitted` check.
- **Permissions** — `RolesAndPermissionsSeeder` seeds `<module>.<action>` for every
  module in the entry's `modules` list, so a route never silently becomes
  world-readable. Custom state actions map onto the CRUD suffixes in
  `EnsurePermitted::ACTION_MAP` (`publish`/`resolve`/`close`/`assign`/`share`/
  `respond`/`retire`/`cancel` … → `update`; `submit` → `create`).
- **Webhooks / metrics** — models use `DispatchesWebhooks` + `RecordsActivity`, so
  lifecycle events and the audit trail flow without extra wiring.

## 2. Invariants per tool

- **Form Builder** — a form starts `draft` with `submissions_count = 0`; `publish`
  moves `draft → active` (archived forms reject); fields are typed (`text`, `email`,
  `select`, `checkbox`, …); a submission stores its `payload` and increments the
  form's counter; archived forms reject submissions.
- **Messenger** — conversations open by default and move `open ⇄ closed`; `reopen`
  refuses archived conversations; posting a message stamps `last_message_at` on the
  conversation and sets `direction` (`in`/`out`).
- **Document Manager** — folders form a tree; deleting a folder with children or
  documents returns `422`; documents start at `version = 1` and bump on content/url
  edits; `share` merges unique user ids into `shared_with`.
- **Support Tickets** — `ticket_number` is generated per company as `TKT-YY-0001`;
  lifecycle `open → resolved → closed` with `reopen`; resolving/ replying to a closed
  ticket returns `422`; replies carry an `is_internal` flag.
- **Calendar & Events** — `ends_at` must be `after_or_equal:starts_at`; events can be
  cancelled once; attendees need at least one of `user_id`/`contact_id`/`email` and
  RSVP `invited → accepted | declined | tentative`.
- **Asset Management** — `asset_tag` is unique per company; `assign` sets status
  `assigned` and writes an `asset_assignments` row; `return` sets `available` and
  closes the open assignment; `retire` is terminal.

## 3. Local verification

```powershell
# api/
php artisan migrate:fresh --seed        # tables + demo data + tool permissions
php vendor\bin\pint --test
php -d memory_limit=2G vendor\bin\phpstan analyse --no-progress
php artisan test --filter='FormBuilderTest|MessengerTest|DocumentManagerTest|SupportTicketsTest|CalendarEventsTest|AssetManagementTest'
```

## 4. End-to-end smoke test (API only)

```powershell
$base = "http://localhost:8000/api/v1"
$t = (Invoke-RestMethod "$base/login" -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t" }

# Menu source of truth: catalog + effective toggle state
(Invoke-RestMethod "$base/addons" -Headers $h).data | Where-Object id -eq "form-builder"

# Create a form (draft) and publish it
$form = (Invoke-RestMethod "$base/forms" -Method Post -Headers $h -ContentType application/json `
          -Body (@{name="Support intake"} | ConvertTo-Json)).data
$form.status                                  # draft
(Invoke-RestMethod "$base/forms/$($form.id)/publish" -Method Post -Headers $h).data.status   # active

# Toggle the add-on off and confirm the guard
Invoke-RestMethod "$base/addons/form-builder" -Method Put -Headers $h -ContentType application/json `
  -Body (@{enabled=$false} | ConvertTo-Json) | Out-Null
try { Invoke-RestMethod "$base/forms" -Headers $h } catch { $_.ErrorDetails.Message }  # addon_disabled

# Switch it back on
Invoke-RestMethod "$base/addons/form-builder" -Method Put -Headers $h -ContentType application/json `
  -Body (@{enabled=$true} | ConvertTo-Json) | Out-Null
```

The API reference is generated with Scribe: `php artisan scribe:generate` (serve the
`laravel`-type docs at `/docs`). Every mutation is captured on `GET /api/v1/audit-logs`
and emitted to subscribed webhook endpoints.
