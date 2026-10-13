# Phase 11 — Vertical Solutions (Add-ons) — Pilot Runbook

This phase documents the four **Vertical Solutions** add-ons: `coworking-space`,
`laundry-dry-cleaning`, `gym-management` and `rental`. Each is an opt-in add-on any
company can switch on, following the same add-on contract as the cross-domain tools
in `scripts/tools-pilot.md`. One `config/addons.php` entry drives the catalog
listing, the per-company toggle, the permission seeding and the route gating.

| Add-on id | Display | Permission modules | Prefix |
|---|---|---|---|
| `coworking-space` | Coworking Space | `coworking-units`, `coworking-bookings` | `/api/v1/coworking-*` |
| `laundry-dry-cleaning` | Laundry & Dry Cleaning | `laundry-orders`, `laundry-items` | `/api/v1/laundry-*` |
| `gym-management` | Gym Management | `gym-plans`, `gym-members`, `gym-visits` | `/api/v1/gym-*` |
| `rental` | Rental Management | `rental-agreements`, `rental-items` | `/api/v1/rental-*` |

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
  `EnsurePermitted::ACTION_MAP` (`confirm`/`check-in`/`check-out`/`cancel`/`advance`/
  `suspend`/`activate`/`return` → `update`; `summary`/`availability` →
  `view-any`).
- **Webhooks / metrics** — models use `DispatchesWebhooks` + `RecordsActivity`; every
  service transition also calls `MetricsService::recordVertical(<vertical>, <state>)`.
  All models are `CompanyScoped` and `SoftDeletes`.

## 2. Coworking Space

Modules: `coworking-units`, `coworking-bookings`. Routes (addon-gated):
- `GET /api/v1/coworking-units/availability` (range + seats feed)
- `GET/POST/PUT/PATCH/DELETE /api/v1/coworking-units*`, `POST /api/v1/coworking-units/{id}/restore`
- `GET /api/v1/coworking-bookings/summary`
- `GET/POST/PUT/PATCH/DELETE /api/v1/coworking-bookings*`, `POST /api/v1/coworking-bookings/{id}/restore`
- `POST /api/v1/coworking-bookings/{id}/confirm|check-in|check-out|cancel`

Invariants (`CoworkingBookingService`):
- A booking holds its unit slot only while live; `ACTIVE_STATUSES` =
  `requested`, `confirmed`, `checked_in`. `checked_out`/`cancelled` release the slot.
- Double-booking is rejected by an overlap query `start_at < end AND end_at > start`
  scoped to company + unit (optionally excluding the booking being edited).
- Lifecycle `requested → confirmed → checked_in → checked_out`, with `requested`/
  `confirmed → cancelled`; anything not in `TRANSITIONS` is terminal. `check-in` on a
  booking whose `end_at` has passed is rejected.
- Price is hourly (`rate_hourly`) below a day, otherwise `rate_daily` × days; the unit
  must be active and `capacity >= seats` to be `available`.

## 3. Laundry & Dry Cleaning

Modules: `laundry-orders`, `laundry-items`. Routes (addon-gated):
- `GET /api/v1/laundry-orders/summary`
- `GET/POST/PUT/PATCH/DELETE /api/v1/laundry-orders*`, `POST /api/v1/laundry-orders/{id}/restore`
- `POST /api/v1/laundry-orders/{id}/advance|cancel`
- `GET/POST/PUT/PATCH/DELETE /api/v1/laundry-items*`, `POST /api/v1/laundry-items/{id}/restore`

Invariants (`LaundryOrderService`):
- `order_number` is company-scoped, e.g. `LW-YYMM-0001`.
- `recalculate` derives money from live items: `subtotal = Σ line subtotals`,
  `tax = round(subtotal × tax_rate, 2)`, `total = subtotal + tax − discount`.
- `advance` walks the wash/dry/fold/pickup lifecycle; `delivered` is terminal and
  stamps `completed_at`. `cancel` is refused once `delivered`/`cancelled`.

## 4. Gym Management

Modules: `gym-plans`, `gym-members`, `gym-visits`. Routes (addon-gated):
- `GET/POST/PUT/PATCH/DELETE /api/v1/gym-plans*`, `POST /api/v1/gym-plans/{id}/restore`
- `GET /api/v1/gym-members/summary`
- `GET/POST/PUT/PATCH/DELETE /api/v1/gym-members*`, `POST /api/v1/gym-members/{id}/restore`
- `POST /api/v1/gym-members/{id}/suspend|activate`
- `GET/POST/PUT/PATCH/DELETE /api/v1/gym-visits*`, `POST /api/v1/gym-visits/{id}/check-out`,
  `POST /api/v1/gym-visits/{id}/restore`

Invariants (`GymService`):
- `member_number` is company-scoped, e.g. `GM-YY-0001`.
- Check-in requires a live membership and, when the plan sets
  `max_monthly_visits`, fewer than that many visits logged this calendar month.
- A cancelled member can be neither suspended nor reactivated; suspending a live member
  sets `suspended`, activating sets `active`.
- Check-out is refused if the visit already has `checked_out_at`.

## 5. Rental Management

Modules: `rental-agreements`, `rental-items`. Routes (addon-gated):
- `GET /api/v1/rental-agreements/summary`
- `GET/POST/PUT/PATCH/DELETE /api/v1/rental-agreements*`, `POST /api/v1/rental-agreements/{id}/restore`
- `POST /api/v1/rental-agreements/{id}/activate|cancel`
- `GET/POST/PUT/PATCH/DELETE /api/v1/rental-items*`, `POST /api/v1/rental-items/{id}/return`,
  `POST /api/v1/rental-items/{id}/restore`

Invariants (`RentalAgreementService`):
- `agreement_number` is company-scoped, e.g. `RN-YYYY-0001`.
- Only a `draft` agreement can be activated; `returned`/`cancelled` are terminal and a
  `returned` or `cancelled` agreement cannot be cancelled.
- Items return only on an `active` agreement and only once; `return` stamps
  `returned_at` + `return_condition` (defaults to `good`). When every item is back the
  agreement flips to `returned`.

## 6. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes config\addons.php
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='CoworkingTest|LaundryTest|GymTest|RentalTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: Coworking 21, Laundry 14, Gym 13, Rental 15 — 63 passed (281 assertions);
`PermissionCoverageTest` 5 passed. Full suite remains green (1 skipped, 1106 passed).

## 7. Notes

- These are pure add-ons: no `addon:`-independent core surface, no cross-cutting
  changes were required in this phase — the runbook documents the shipped contract and
  confirms test coverage.
- The four verticals are cross-referenced from `scripts/tools-pilot.md` and the add-on
  table in `scripts/features-pilot.md`; this runbook is their dedicated reference.
