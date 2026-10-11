# Phase 6 — Integrations Connector Framework — Pilot Runbook

A settings-gated connector framework that turns the provider catalog in
`config/business-integrations.php` into a connectable integrations surface.
Unlike the add-ons, this is **not** per-company toggled — it is always present
for companies whose staff hold the `integrations.*` permissions. It follows the
same conventions as the Business Settings surface (keyed by a string `{provider}`
rather than an id, plain JSON envelopes rather than a resource collection).

| Module | Routes | Permission |
|---|---|---|
| `integrations` | `GET /api/v1/integrations` | `integrations.view-any` |
| | `GET /api/v1/integrations/{provider}` | `integrations.view` |
| | `PUT /api/v1/integrations/{provider}` | `integrations.update` |
| | `POST /api/v1/integrations/{provider}/connect` | `integrations.update` |
| | `POST /api/v1/integrations/{provider}/disconnect` | `integrations.update` |
| | `POST /api/v1/integrations/{provider}/test` | `integrations.update` |

`EnsurePermitted` gained two `ACTION_MAP` entries (`connect`, `disconnect` →
`update`; `test` already mapped to `update`). `RolesAndPermissionsSeeder` seeds
`integrations` under `$partialActions` as `['view-any', 'view', 'update']`.

## 1. Storage model

- **Credentials** — stored in the `business-settings` group that shares the
  provider key (e.g. the `stripe` group). The `enabled` key is the toggle.
  `IntegrationGroupDefinitions` already generates one typed group per provider,
  so no new catalog is needed.
- **Connection state** — the `integrations` table, one row per
  `(company_id, provider)`: `status`, `is_connected`, `connected_at`,
  `last_tested_at`, `last_test_status`, `meta`, `created_by`. The row is created
  lazily on the first write and carries `CompanyScoped`, `DispatchesWebhooks`,
  `RecordsActivity`.

## 2. Invariants

- **States** — `configuration_required` (default; enabled but credentials
  incomplete), `connected` (enabled and every required credential present), and
  `disabled` (explicitly disconnected). `is_connected` is true only in the
  `connected` state.
- **Required fields** — every `secret` field is required; a provider with no
  secret fields (e.g. `einvoice`) requires all of its fields. `connect` refuses
  an incompletely configured provider with `422 {missing_fields:[...]}`.
- **Secrets are masked** — `show`/`PUT` return `********` for a populated secret
  and `""` for an empty one, plus `filled`/`required` per field. A blank secret
  in an update is ignored, so saving screens without re-typing secrets never
  clears them.
- **No outbound calls** — `connect` only validates stored credentials; `test`
  stamps `last_tested_at`/`last_test_status=success` and reports the stored
  state. A disconnected provider answers `422` on test.
- **Company isolation** — settings are read/written per `company_id` and the
  connection row is looked up by explicit `company_id`, so one company's
  connection is invisible to another.

## 3. Local verification

```powershell
# api/
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='IntegrationTest|PermissionCoverageTest'
```

## 4. API smoke test

```powershell
$t = (Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t" }

# Catalog (meta.categories groups the providers)
(Invoke-RestMethod http://localhost:8000/api/v1/integrations -Headers $h).data |
  Where-Object provider -eq 'stripe' | ConvertTo-Json -Compress

# Save credentials (secrets come back masked)
$d = (Invoke-RestMethod http://localhost:8000/api/v1/integrations/stripe -Method Put -Headers $h -ContentType application/json `
       -Body (@{publishable_key="pk_test_123";secret_key="sk_test_123";webhook_secret="whsec_123"} | ConvertTo-Json)).data
$d.status      # configuration_required
$d.fields | Where-Object name -eq 'secret_key' | Select-Object value   # ********

# Connect → connected, then test
(Invoke-RestMethod http://localhost:8000/api/v1/integrations/stripe/connect -Method Post -Headers $h).data.status      # connected
(Invoke-RestMethod http://localhost:8000/api/v1/integrations/stripe/test -Method Post -Headers $h).data.last_test_status # success

# Disconnect keeps credentials but flips the state
(Invoke-RestMethod http://localhost:8000/api/v1/integrations/stripe/disconnect -Method Post -Headers $h).data.status   # disabled
```

The API reference is generated with Scribe: `php artisan scribe:generate` (serve
the `laravel`-type docs at `/docs`). Connection-state writes are captured on
`GET /api/v1/audit-logs` and emitted to subscribed webhook endpoints.
