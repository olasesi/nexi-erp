# Phase 5 Add-on — Bookings — Pilot Runbook

A **bookings** add-on that lets a company schedule time on bookable resources
(meeting rooms, vehicles, equipment, services) while preventing double-booking.
It follows the same `config/addons.php` contract — one entry drives the catalog
listing, the per-company toggle, the permission seeding and the route gating.

| Add-on id | Display | Permission modules | Prefix |
|---|---|---|---|
| `bookings` | Bookings | `booking-resources`, `bookings` | `/api/v1/booking-resources*`, `/api/v1/bookings*` (+ `confirm`, `check-in`, `complete`, `cancel`, `no-show`) |

## 1. Add-on contract

Identical to `tools-pilot.md`, `phase1-pilot.md`, `phase2-pilot.md`,
`phase3-pilot.md` and `phase4-pilot.md` — catalog (`GET /api/v1/addons`), toggle
(`PUT /api/v1/addons/{id} {enabled:bool}`), `addon:<id>` route gating returning
`403 {code:"addon_disabled", addon:"bookings"}`, and permission seeding from the
entry's `module` + `actions` plus the `modules` list. `EnsurePermitted::ACTION_MAP`
needs one new entry: `no-show` → `update` (`confirm`, `check-in`, `complete`,
`cancel` already map to `update`).

## 2. Invariants per add-on

- **Bookings** — self-contained module with two parts:
  - **Booking resource** (`booking-resources`) — a bookable unit with a `type`
    (`room`, `vehicle`, `equipment`, `service`) and an `is_active` flag. The
    controller defaults a missing `type` to `room` and `is_active` to `true`.
    Resources are soft-deletable and restorable; the list is filterable by `type`
    and searchable by `name`.
  - **Booking** (`bookings`) — a time-boxed reservation against a resource:
    `title`, `customer_name`, optional `contact_id` and `notes`,
    `starts_at`/`ends_at` and a lifecycle `status`
    (`pending → confirmed → checked_in → completed`, plus `cancelled` and
    `no_show`). `canTransitionTo()` guards every move, so `pending` cannot jump
    straight to `check-in`, a `completed` booking is read-only, and a cancelled
    or no-show booking cannot be reopened.
  - **Overlap protection** — creating or editing a booking runs
    `BookingService::hasConflicts()` over active statuses (`pending`, `confirmed`,
    `checked_in`) on the same resource; a conflicting window is refused with a
    `422` on `resource_id`. Adjacent (touching) windows are allowed, and cancelling
    a booking releases its slot. `update()`/`destroy()` refuse non-pending/confirmed
    bookings.

## 3. Local verification

```powershell
# api/
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='BookingResourceTest|BookingTest'
```

## 4. API smoke test

```powershell
$t = (Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t" }

# Resource
$r = (Invoke-RestMethod http://localhost:8000/api/v1/booking-resources -Method Post -Headers $h -ContentType application/json `
       -Body (@{name="Meeting Room A";type="room"} | ConvertTo-Json)).data

# Booking + lifecycle
$b = (Invoke-RestMethod http://localhost:8000/api/v1/bookings -Method Post -Headers $h -ContentType application/json `
       -Body (@{resource_id=$r.id;title="Sprint planning";customer_name="Acme";
                starts_at="2026-11-02 09:00:00";ends_at="2026-11-02 10:00:00"} | ConvertTo-Json)).data
Invoke-RestMethod "http://localhost:8000/api/v1/bookings/$($b.id)/confirm"   -Method Post -Headers $h | Out-Null
Invoke-RestMethod "http://localhost:8000/api/v1/bookings/$($b.id)/check-in" -Method Post -Headers $h | Out-Null
(Invoke-RestMethod "http://localhost:8000/api/v1/bookings/$($b.id)/complete" -Method Post -Headers $h).data.status  # completed

# Overlap is refused (422 on resource_id)
try {
  Invoke-RestMethod http://localhost:8000/api/v1/bookings -Method Post -Headers $h -ContentType application/json `
    -Body (@{resource_id=$r.id;title="Clash";starts_at="2026-11-02 09:30:00";ends_at="2026-11-02 10:30:00"} | ConvertTo-Json)
} catch { $_.ErrorDetails.Message }

# Add-on guard
Invoke-RestMethod http://localhost:8000/api/v1/addons/bookings -Method Put -Headers $h -ContentType application/json `
  -Body (@{enabled=$false} | ConvertTo-Json) | Out-Null
try { Invoke-RestMethod http://localhost:8000/api/v1/bookings -Headers $h } catch { $_.ErrorDetails.Message }  # addon_disabled
```

The API reference is generated with Scribe: `php artisan scribe:generate` (serve the
`laravel`-type docs at `/docs`). Every mutation is captured on `GET /api/v1/audit-logs`
and emitted to subscribed webhook endpoints.
