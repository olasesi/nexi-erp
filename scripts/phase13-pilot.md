# Phase 13 — Webhooks & Event Delivery (Core Surface) — Pilot Runbook

This phase documents the core **outbound webhooks** surface: subscriptions
(`webhook-endpoints`), the subscribable event catalogue (`webhook-events`) and the
delivery log (`webhook-deliveries`). It is part of the core staff-authenticated API
(not gated by `addon:` middleware); `webhook-endpoints` and `webhook-deliveries` are
`CompanyScoped`, and `webhook-endpoints` records activity via `RecordsActivity`.

Routes (core, `auth:api` + `staff` + `permitted`):
- `GET /api/v1/webhook-events`
- `GET/POST/PUT/PATCH/DELETE /api/v1/webhook-endpoints*`
- `GET /api/v1/webhook-endpoints/{id}/deliveries`
- `POST /api/v1/webhook-endpoints/{id}/test`, `POST /api/v1/webhook-endpoints/{id}/rotate-secret`
- `GET /api/v1/webhook-deliveries`, `GET /api/v1/webhook-deliveries/{id}`
- `POST /api/v1/webhook-deliveries/{id}/redeliver`

`EnsurePermitted::ACTION_MAP`: `deliveries` → `view`, `events` → `view-any`,
`test`/`rotate-secret`/`redeliver` → `update`.

## 1. Endpoints

`WebhookEndpoint` has `name`, `url`, `secret`, `events` (JSON list), `is_active`
and `description`. A null `company_id` makes the endpoint **global**. `secret` is
`$hidden` and generated with `Str::random(48)` on create; `rotate-secret` issues a
fresh one. `store` validates that every event is in the catalogue and that the URL
is HTTPS (plain HTTP is allowed only for loopback addresses). `index` supports
`company_id` / `is_active` filters and exposes `deliveries_count`.

## 2. Event catalogue

`WebhookService::events()` is the union of:
- **Lifecycle** — `{subject}.{created|updated|deleted|restored}` for every subject in
  `LIFECYCLE_SUBJECTS` (invoice, payment, contact, product, quotation, sales_order,
  purchase_order, purchase_receipt, stock_transfer, stock_adjustment, expense,
  income, bank_transaction, reconciliation, crm_stage, lead, deal, project,
  project_task, project_milestone, pos_register, pos_order, user).
- **Domain events** — `DOMAIN_EVENTS`: `invoice.sent`, `payment.completed`,
  `quotation.accepted`, `quotation.rejected`, `purchase_receipt.received`,
  `stock_transfer.completed`, `stock_adjustment.posted`, `reconciliation.matched`,
  `lead.converted`.

`IGNORED_SUBJECTS` (audit_log, webhook_endpoint, webhook_delivery, setting,
notification, currency_rate, passport_token) never generate deliveries. `GET
/webhook-events` returns `{name, group}` so clients can render a picker.

## 3. Dispatch & payload

`WebhookService::dispatchLifecycle` (from the `DispatchesWebhooks` trait) and explicit
service calls fan an event out to every **active** endpoint whose `events` JSON
contains it (`scopeSubscribedTo`). An endpoint with a null company receives all
events; otherwise only the model's company plus the global endpoints are called.

The payload `{event, sent_at, data:{id, type, company_id, attributes}}` is stored
verbatim on the delivery. `SENSITIVE` keys (password, remember_token, secret,
password_hash, api_key) are stripped from `attributes`; a domain call may pass its
own `attributes` instead.

## 4. Delivery & retries

Each fan-out creates a `webhook_delivery` (`pending`, `attempts = 0`) and hands it to
the configured driver (`config/webhooks.php`): `sync` delivers in-process, `queue`
pushes a `DeliverWebhook` job. `WebhookService::attempt` POSTs the body with
`X-Nexi-Event`, `X-Nexi-Delivery`, `X-Nexi-Timestamp`, `X-Nexi-Attempt` and
`X-Nexi-Signature` headers.

- Signature is `sha256=HMAC-SHA256("{timestamp}.{body}", secret)`; `verifySignature`
  rejects stale timestamps beyond `signature_tolerance` (default 300s).
- On failure the delivery is re-queued with `next_attempt_at` from `backoff`
  (`[60,300,900,3600]`); once `attempts` reaches `max_attempts` it becomes `failed`.
- Success sets `succeeded` + `delivered_at`; the response body is truncated to
  `response_limit` characters.
- `POST /webhook-deliveries/{id}/redeliver` resets a non-`succeeded` delivery to
  `pending` and attempts it again (422 if already succeeded). `test` dispatches a
  synthetic `webhook_endpoint` payload **without** touching the catalogue.

States: `pending`, `succeeded`, `failed`; `scopeRetryable` selects `pending`
deliveries whose `next_attempt_at` is null or past.

## 5. Verification

```powershell
cd api
php artisan migrate:fresh --seed
php vendor/bin/pint app tests database routes config\webhooks.php
php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress
php artisan test --filter='WebhookTest|WebhookDomainEventTest'
php artisan test --filter=PermissionCoverageTest
```

Expected: `WebhookTest` 17 + `WebhookDomainEventTest` 9 — 26 passed (118 assertions);
`PermissionCoverageTest` 5 passed. Full suite remains green.

## 6. Notes

- Webhooks are a core module: `webhook-endpoints` is a full module, while
  `webhook-deliveries` (`view-any`/`view`/`update`) and `webhook-events` (`view-any`)
  are seeded as partial modules in `RolesAndPermissionsSeeder::$partialActions`.
- The `webhooks:retry` command replays retryable deliveries; `webhooks:prune` clears
  finished deliveries older than `retention_days` (pending ones are never pruned).
- Every other pilot runbook relies on this service for its "captured on audit-logs
  and emitted to subscribed webhook endpoints" claim.
