# Nexi ERP API

Laravel 12 JSON API for Nexi ERP. Every business route lives under
`/api/v1`, authenticated with **Laravel Passport (OAuth2 password grant)** and
authorised through the role/permission tables.

## Requirements

- PHP 8.2+
- Composer 2
- MySQL (or any database the project migrations support)
- Node is *not* required to run the API

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan passport:keys
php artisan passport:client --password --name="Nexi ERP Password Grant"
php artisan serve
```

Copy the password client's credentials into `.env`:

```dotenv
PASSPORT_PASSWORD_CLIENT_ID=...
PASSPORT_PASSWORD_CLIENT_SECRET=...
```

Authentication flow:

| Endpoint                | Purpose                                            |
| ----------------------- | -------------------------------------------------- |
| `POST /api/v1/login`    | Exchange credentials for an access/refresh token   |
| `POST /api/v1/register` | Staff sign-up                                       |
| `GET  /api/v1/user`     | Current user                                        |
| `POST /api/v1/logout`   | Revoke the current access token                     |

Send the token on every other call: `Authorization: Bearer <access_token>`.

Customer portal accounts use `POST /api/v1/customer/register` and the
`/api/v1/customer/*` routes, which are guarded by the `customer` middleware.

## Conventions

- Success responses wrap payloads in `data`; list endpoints return `data`,
  `meta` and `links` (pagination).
- Errors return `message` plus a `errors` object keyed by field.
- Soft-deleted resources stay hidden and are brought back with the
  `POST /api/v1/{resource}/{id}/restore` action.
- Money is returned as a decimal string; dates as ISO-8601.
- `company_id = null` means the row is global and visible to every company.

## Permissions

Roles are `admin`, `manager`, `accountant`, `sales`, `purchases`, `warehouse`,
`viewer` and `customer` (see `app/Database/Seeders`). A user without the
`X-Api-Permission` ability for an action receives `403`. The action → ability
map lives in `app/Http/Middleware/EnsurePermitted.php`.

## Import & export

`import` and `export` actions are available on contacts, products, bank
transactions and invoices. Uploads are parsed by `App\Support\Csv` (streaming,
header driven), rows are validated per row and a `*.import_errors` file is
written next to the template when something is rejected.

## Webhooks

`POST /api/v1/webhook-endpoints` subscribes a consumer to the event catalogue
exposed by `GET /api/v1/webhook-events`. Endpoints must be HTTPS (loopback may
use HTTP), receive a 48 character signing secret and are called with:

| Header             | Meaning                                     |
| ------------------ | ------------------------------------------- |
| `X-Nexi-Event`     | Event name, e.g. `invoice.created`          |
| `X-Nexi-Delivery`  | Delivery id (replay/inspect with it)        |
| `X-Nexi-Attempt`   | 1 based attempt counter                     |
| `X-Nexi-Timestamp` | Unix seconds of the attempt                 |
| `X-Nexi-Signature` | `sha256=<hmac>` of `{timestamp}.{body}`     |

Verify a payload with `WebhookService::verifySignature($secret, $timestamp,
$body, $signature)`, which also enforces the replay window
(`WEBHOOK_SIGNATURE_TOLERANCE`, default 300s).

Every attempt is stored on `webhook_deliveries`, so `GET
/api/v1/webhook-endpoints/{endpoint}/deliveries` and the replay action
(`POST /api/v1/webhook-deliveries/{delivery}/replay`) work for failures.

## Scheduled commands

```bash
php artisan webhooks:retry    # attempt deliveries whose backoff elapsed
php artisan webhooks:prune    # delete finished deliveries past the retention window
php artisan schedule:list     # both are registered in bootstrap/app.php
```

Run the scheduler once a minute (`* * * * * cd /path/to/api && php artisan
schedule:run`). With `WEBHOOK_DRIVER=sync` the retry command is what retries
failed deliveries; with `WEBHOOK_DRIVER=queue` the queue worker does it and the
command is only a safety net.

## Versioning

`config/api.php` publishes the versions the API serves. A request selects a
version through the path (`/api/v1/...`), the media type
(`Accept: application/vnd.nexi.v1+json`) or the `X-Api-Version` header; the path
wins. Every response carries `X-Api-Version`, and `GET /api/versions` lists the
catalogue. A version marked `deprecated` answers with the RFC 8594 `Deprecation`,
`Sunset`, `Link` and `Warning` headers, and once its sunset date passes it is
answered with `410 Gone`.

## Observability

| Endpoint          | Purpose                                                              |
| ----------------- | -------------------------------------------------------------------- |
| `GET /api/health` | Dependency probes (database, cache, storage, queue) with latencies     |
| `GET /api/metrics`| Prometheus text exposition (`text/plain; version=0.0.4`)              |

`/api/health` returns `503` only when a critical dependency (the database) is
down; a failing cache, disk or queue degrades the status to `degraded` and still
returns `200`. `/api/metrics` exposes request counters and latency histograms,
auth counters, user/notification gauges, webhook delivery gauges and
`nexi_erp_health{component="…"}`.

## Quality tooling

```bash
php artisan test                                   # or vendor/bin/pest
vendor/bin/pest --filter=WebhookTest               # a single suite
vendor/bin/phpstan analyse --no-progress           # level 8 + Larastan
vendor/bin/pint --test                             # code style check
```

PHPStan runs on `app/` and `config/` at level 8; new code must not add entries to
`phpstan-baseline.neon`.

## Layout

```
api/
├── app/
│   ├── Console/Commands/     # webhooks:retry, webhooks:prune
│   ├── Http/Controllers/Api  # V1 resource controllers + health/metrics
│   ├── Http/Middleware/      # EnsurePermitted, CollectMetrics, ApiVersion, …
│   ├── Jobs/                 # DeliverWebhook
│   ├── Models/               # Eloquent models, Concerns/{CompanyScoped,…}
│   ├── Services/             # business logic (Accounting, Webhook, Metrics, …)
│   └── Support/              # Csv, ApiVersions, PermissionGuard
├── config/                   # api.php, webhooks.php, …
├── database/migrations       # schema, including 2026_09_28_000008 webhooks
├── routes/api.php            # /api, /api/v1/*
└── tests/                    # Pest feature + integration suites
```
