# Phase 28 — AI Business Advisor (Add-on `business-advisor`) — Pilot Runbook

This phase documents the **AI Business Advisor** add-on (`business-advisor`): a
health scorecard computed live from a company's own data, plus a stored snapshot
history for trend analysis.

Routes (add-on `business-advisor`, then `business-advisor.view-any` / `.view`):
- `GET /api/v1/business-advisor/report` — the live scorecard
- `GET /api/v1/business-advisor/snapshots` — stored snapshot history (trend)

Add-on entry `business-advisor` (`category` AI, `module` `business-advisor`,
`actions` `view-any`, `view`, `enabled_by_default` true). Requests without a
company on the acting user return a `422` (`noCompany`).

## 1. Live scorecard (`report`)

`BusinessHealthService::report` computes a scorecard from the company's own data:

- **Categories** — financial, sales, operations, team (and projects), each scored
  `0–100` and weighted via `CATEGORY_WEIGHTS`. A category with no underlying data
  is reported as a **note** rather than a score. The **overall score is withheld**
  until at least `MIN_SCORED_CATEGORIES` (2) categories have data.
- **Alerts / insights / recommendations** are derived from real numbers:
  - out-of-stock products raise a **critical** alert with a restock recommendation;
  - overdue invoices recommend collection;
  - a company with no activity reports only the "quiet team" alert.
  - `topExpenseCategory`, `trend`, `delta` and a 30-day `WINDOW_DAYS` inform the
    narrative.
- Signal windows use statuses from Phase 19/20 (`COUNTED_STATUSES = paid, partial,
  sent, overdue`; `OPEN_SALES_ORDER_STATUSES = draft, confirmed, processing`).

## 2. Snapshots & trend

`BusinessHealthService::record` stores a `BusinessHealthSnapshot` per company/day
(`score_date`, `overall_score` + per-category scores, and `signals`/`alerts`/
`insights`/`recommendations` array casts). `scopeRecent` powers the 7-day trend in
`GET /business-advisor/snapshots`, and `delta` compares the current score against
the previous stored score. `record` also **notifies the company once** when
critical alerts appear.

The nightly command **`advisor:snapshot`** scores every company whose
`business-advisor` add-on is enabled (checked via `AddonService::enabled`); a
company that has switched the add-on off is left unscored.

Invariants: `BusinessHealthSnapshot` uses `DispatchesWebhooks` + `HasFactory` and is
keyed by `company_id`.

## 3. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter=BusinessAdvisorTest
php artisan test --filter=PermissionCoverageTest
```

Expected: `BusinessAdvisorTest` 14 passed (61 assertions). `PermissionCoverageTest`
5 passed. Full suite remains green.

## 4. Notes

- The add-on gates both endpoints; a user without the `business-advisor`
  permissions gets a `403`, and a switched-off add-on gets
  `403 {code:"addon_disabled"}` before the permission check.
- The report is computed live on each call; only the trend/history comes from
  stored snapshots written by `advisor:snapshot`.
