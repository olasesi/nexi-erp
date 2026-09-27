# Notifications Feature — Pilot Runbook

Use the **Notifications feature** as another repeatable vertical slice: develop →
test → deploy → scale → monitor, observed **via the API alone**. Everything below
works with `curl` / PowerShell / `kubectl`.

The Notifications slice covers an **in-app inbox** scoped to a company:
- `GET /api/v1/notifications` — paginated list visible to the caller (company
  broadcasts plus notifications addressed to the caller), with `unread`, `type`,
  and `search` filters.
- `GET /api/v1/notifications/unread-count` — the bell badge value.
- `POST /api/v1/notifications` — send a broadcast or a personally-addressed
  notification (`user_id`) with `type`/`title`/`body`/`payload`.
- `GET|PUT /api/v1/notifications/{id}` — view/edit, incl. mark as read via `is_read`.
- `POST /api/v1/notifications/read-all` — mark every visible notification read.
- `DELETE /api/v1/notifications/{id}` — remove a notification.

The feature is gated by the seeded `notifications.*` permissions and publish two
metrics: `nexi_erp_notifications_created_total` (counter) and
`nexi_erp_notifications_total{status="unread"|"read"}` (gauge).

> Cache note: the API's cache store defaults to `database` (see `api/config/cache.php`).
> `/api/metrics` counters are shared across PHP-FPM workers and pods. No Redis required.

---

## 0. Prerequisites

- PHP 8.2+, Composer (backend) · Docker or a Kubernetes cluster
- Locally: `api/.env` has `APP_KEY`, `DB_CONNECTION=sqlite`, oauth keys generated.

---

## 1. Develop + test locally (fast loop, ~1 min)

```powershell
# api/
composer install
php artisan migrate:fresh --seed        # tables + demo data + notifications.* perms + a few seeded notices
php artisan passport:keys --force       # OAuth keys
php artisan passport:client --password --name="Nexi ERP Password Grant"
```

Copy the printed **Client ID / Client secret** into `api/.env`:

```
PASSPORT_PASSWORD_CLIENT_ID=<id>
PASSPORT_PASSWORD_CLIENT_SECRET=<secret>
```

```powershell
php artisan config:clear
php artisan serve                        # http://localhost:8000
```

Run the unit checks, then the end-to-end smoke test:

```powershell
# api/
php vendor\bin\pint --test
php vendor\bin\phpstan analyse --no-progress
php artisan test --filter='NotificationTest'

# repo root
.\scripts\test-notifications.ps1         # expects the server on :8000, cleans up after itself
```

Hand-roll a few calls to see behavior (role: admin):

```powershell
$t = (Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t"; "Content-Type" = "application/json" }

Invoke-RestMethod http://localhost:8000/api/v1/notifications -Headers $h            # inbox
Invoke-RestMethod http://localhost:8000/api/v1/notifications/unread-count -Headers $h   # badge
Invoke-RestMethod http://localhost:8000/api/v1/notifications?unread=1 -Headers $h    # unread only

Invoke-RestMethod http://localhost:8000/api/v1/notifications -Method Post -Headers $h -Body (@{ title="Low stock"; body="SKU-1001 has 3 left"; type="stock" } | ConvertTo-Json)

$first = (Invoke-RestMethod http://localhost:8000/api/v1/notifications -Headers $h).data[0].id
Invoke-RestMethod ("http://localhost:8000/api/v1/notifications/$first") -Method Put -Headers $h -Body (@{ is_read = $true } | ConvertTo-Json)
Invoke-RestMethod http://localhost:8000/api/v1/notifications/read-all -Method Post -Headers $h

Invoke-WebRequest http://localhost:8000/api/metrics | Select-String "nexi_erp_notifications"
```

---

## 2. Deploy the slice with Docker Compose

```powershell
# api/
docker compose up --build -d
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan passport:client --password --name="Nexi ERP Password Grant"
```

Put the client id/secret into the container's `.env` and restart, then smoke through
the compose nginx:

```powershell
# api/
docker compose exec app php artisan config:clear
docker compose restart app
# repo root
.\scripts\test-notifications.ps1 -BaseUrl http://localhost:80
.\scripts\load-test-notifications.ps1 -BaseUrl http://localhost:80 -Concurrency 12 -RequestsPerWorker 40
```

---

## 3. Deploy and scale on Kubernetes

```powershell
# repo root
docker build -t registry.example.com/nexi-erp:latest -f api/Dockerfile api
docker push registry.example.com/nexi-erp:latest

kubectl create secret generic nexi-erp-db --from-literal=host=... --from-literal=database=... --from-literal=username=... --from-literal=password=...
kubectl create secret generic nexi-erp-app --from-literal=key=<APP_KEY> --from-literal=oauth_password_client_id=<id> --from-literal=oauth_password_client_secret=<secret> --from-literal=sentry_dsn=

kubectl apply -f api/deploy/kubernetes/nginx-config.yaml
kubectl apply -f api/deploy/kubernetes/deployment.yaml
kubectl apply -f api/deploy/kubernetes/hpa.yaml
```

Seed + create the OAuth password client exactly once (before scaling up):

```powershell
kubectl exec deploy/nexi-erp -- php artisan migrate:fresh --seed
kubectl exec deploy/nexi-erp -- php artisan passport:client --password --name="Nexi ERP Password Grant"
kubectl create secret generic nexi-erp-app --from-literal=oauth_password_client_id=<id> --from-literal=oauth_password_client_secret=<secret> --dry-run=client -o yaml | kubectl apply -f -
kubectl rollout restart deploy/nexi-erp
```

Run the whole loop against the deployed API and watch it scale:

```powershell
kubectl port-forward svc/nexi-erp 8000:80
.\scripts\test-notifications.ps1 -BaseUrl http://localhost:8000
.\scripts\load-test-notifications.ps1 -BaseUrl http://localhost:8000 -Concurrency 25 -RequestsPerWorker 50
kubectl get hpa nexi-erp -w
kubectl top pods -l app=nexi-erp
```

---

## 4. Monitoring

| What | Where | Response |
|---|---|---|
| App + DB + cache + storage health | `GET /api/health` | JSON `{status, checks}` |
| Prometheus metrics | `GET /api/metrics` | Prometheus text (see below) |
| Errors to Sentry | exceptions via Sentry | needs DSN |

Notification metrics on `/api/metrics`:

```
nexi_erp_notifications_created_total        # counter, notifications ever created
nexi_erp_notifications_total{status="unread"}   # gauge, rows with read_at IS NULL
nexi_erp_notifications_total{status="read"}     # gauge, rows with read_at NOT NULL
```

Useful asks:
- `unread / (unread + read)` → staff engagement with the inbox; a sustained climb can
  mean alerts are being ignored (stock alerts, overdue invoices).
- Delta of `nexi_erp_notifications_created_total` per pod over time → whether HPA is
  distributing write load fairly.

---

## 5. Permissions & roles recap

- Seeded module `notifications` → permissions
  `notifications.view-any/view/create/update/delete`.
- `admin` has all of them; `manager` has all except `*.delete`; the seeded `user`
  role gets `view-any/view/update` so standard staff can read and mark their inbox.
- The `notifications.unread-count` route maps to `notifications.view-any` and
  `notifications.read-all` maps to `notifications.update` — both 403 without the
  matching permission.

## 6. Reusing this pattern for other features

Each new feature ships the same recipe, backend-first:

1. Migration + Model + Factory + FormRequest + Controller + Resource + routes.
2. `api/tests/Feature/Api/V1/<Feature>Test.php` — its own vertical test file.
3. Validation: `pint --test`, `phpstan analyse`, `artisan test --filter=<Feature>`.
4. Add the endpoint checks to a per-feature script (copy `scripts/test-notifications.ps1`).
5. If the feature is observable, expose a counter/gauge from `MetricsService` and assert
   it in a feature test (mirror `NotificationTest` + the `nexi_erp_notifications_total`
   gauge).
6. Deploy once, then rely on the existing deploy/scaling/monitoring (no per-feature infra).