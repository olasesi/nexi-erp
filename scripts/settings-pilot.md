# Settings Feature — Pilot Runbook

Use the **Settings feature** as the repeatable vertical slice: develop → test →
deploy → scale → monitor, observed **via the API alone** (no frontend needed).
Everything below works with `curl` / PowerShell / `kubectl`.

> Cache note: the API's cache store defaults to `database` (see `api/config/cache.php`).
> That means `/api/metrics` counters are shared across PHP-FPM workers and pods, and
> Settings → "cache size" reports `0.00` (no file-store footprint). No Redis required.

---

## 0. Prerequisites

- PHP 8.2+, Composer (backend) · Node 20+ (frontend, only if you want the UI)
- Docker (compose path) or a Kubernetes cluster (kubectl path)
- Locally: `api/.env` has `APP_KEY`, `DB_CONNECTION=sqlite`, oauth keys generated.

---

## 1. Develop + test locally (fast loop, ~1 min)

```powershell
# api/
composer install
php artisan migrate:fresh --seed        # tables + admin@nexi-corp.com / password + demo data
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
php vendor\bin\pest --filter=Settings

# repo root
.\scripts\test-settings.ps1              # expects the server on :8000
```

Hand-roll a few calls to see behavior:

```powershell
$t = (Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t" }
Invoke-RestMethod http://localhost:8000/api/v1/settings -Headers $h                 # all groups + meta
Invoke-RestMethod http://localhost:8000/api/metrics -Headers $h                     # Prometheus text
Invoke-RestMethod http://localhost:8000/api/health                                  # no auth needed
```

---

## 2. Deploy the slice with Docker Compose

```powershell
# api/
docker compose up --build -d            # app (php-fpm:9000) + nginx (:80) + mysql + redis
```

Wait for MySQL to be ready, then seed once:

```powershell
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan passport:client --password --name="Nexi ERP Password Grant"
```

Put the client id/secret into the container's `.env` (or hardcode via an
`environment:` entry in `api/docker-compose.yml`), then:

```powershell
docker compose exec app php artisan config:clear
docker compose restart app
```

Smoke through the compose nginx (port 80 = the compose service `web`):

```powershell
.\scripts\test-settings.ps1 -BaseUrl http://localhost:80
.\scripts\load-test.ps1 -BaseUrl http://localhost:80 -Concurrency 20 -RequestsPerWorker 100
```

Check health and metrics through nginx:

```powershell
Invoke-RestMethod http://localhost:80/api/health
Invoke-WebRequest http://localhost:80/api/metrics | Select-Object -ExpandProperty Content
```

---

## 3. Deploy and scale on Kubernetes

```powershell
# repo root
docker build -t registry.example.com/nexi-erp:latest -f api/Dockerfile api
docker push registry.example.com/nexi-erp:latest

kubectl create namespace default

# secrets referenced by the deployment:
kubectl create secret generic nexi-erp-db --from-literal=host=... --from-literal=database=... --from-literal=username=... --from-literal=password=...
kubectl create secret generic nexi-erp-app --from-literal=key=<APP_KEY> --from-literal=sentry_dsn=   # sentry_dsn optional

kubectl apply -f api/deploy/kubernetes/nginx-config.yaml
kubectl apply -f api/deploy/kubernetes/deployment.yaml
kubectl apply -f api/deploy/kubernetes/hpa.yaml
```

Seed + create the OAuth password client exactly once:

```powershell
kubectl exec deploy/nexi-erp -- php artisan migrate:fresh --seed
kubectl exec deploy/nexi-erp -- php artisan passport:client --password --name="Nexi ERP Password Grant"
kubectl create secret generic nexi-erp-app --from-literal=oauth_password_client_id=<id> --from-literal=oauth_password_client_secret=<secret>
```

> The deployment does not yet read those two oauth secret keys from env — to wire
> them, add `PASSPORT_PASSWORD_CLIENT_ID/SECRET` `secretKeyRef`s to
> `api/deploy/kubernetes/deployment.yaml` (mirroring `APP_KEY`).

Expose locally and run the whole loop against the **deployed** API:

```powershell
kubectl port-forward svc/nexi-erp 8000:80
.\scripts\test-settings.ps1 -BaseUrl http://localhost:8000
.\scripts\load-test.ps1 -BaseUrl http://localhost:8000 -Concurrency 30 -RequestsPerWorker 200
```

**Watch it scale** (new terminal):

```powershell
kubectl get hpa nexi-erp -w            # 3 -> 10 replicas once CPU crosses 70%
kubectl get pods -l app=nexi-erp -w
kubectl top pods -l app=nexi-erp       # CPU/memory per pod
```

> HPA uses CPU/memory resource metrics (metrics-server), independent of `/api/metrics`.
> Because each pod's counters share the database cache store, you can also watch the
> aggregate request counter climb as pods are added.

---

## 4. Monitoring

| What | Where | Response |
|---|---|---|
| App + DB + cache + storage health | `GET /api/health` | JSON `{status, checks}` |
| Liveness (deployment) | `GET /up` | 200 plaintext `ok` |
| Prometheus metrics | `GET /api/metrics` | Prometheus text (see below) |
| Errors to Sentry | exceptions via `SentryIntegration::handles()` | needs DSN |
| Laravel logs | `api/storage/logs/laravel.log` | lines per request/error |

`/api/metrics` exposes (all via the shared cache store):

```
nexi_erp_up                                  # gauge, always 1
nexi_erp_uptime_seconds
nexi_erp_build_info{version="..."}
nexi_erp_http_requests_total                 # counter, all requests
nexi_erp_http_request_errors_total           # counter, status >= 500
nexi_erp_http_request_duration_seconds{...}  # histogram (buckets + sum + count)
nexi_erp_health{component="database|cache|storage"}
```

The manifests carry `prometheus.io/scrape=true`, `port=80`, `path=/api/metrics` on the
Service and pods, so a prometheus-operator/kube-prometheus stack picks them up without
extra config. Without Prometheus you can still watch:

```powershell
kubectl port-forward svc/nexi-erp 9000:80
Invoke-WebRequest http://localhost:9000/api/metrics | Select-Object -ExpandProperty Content
```

**Sentry (optional):** put a DSN in `api/.env` (`SENTRY_LARAVEL_DSN=...`) for local runs;
in K8s add it to the `nexi-erp-app` secret (`sentry_dsn`) — the deployment already
injects it (`optional: true`). Trigger an error (e.g. a route that throws) and watch the
unhandled exception appear in the Sentry dashboard a few seconds later.

---

## 5. Reusing this pattern for other features

Each new feature ships the same recipe, backend-first:

1. Migration + Model + Factory + FormRequest + Controller + Resource + routes.
2. `api/tests/Feature/Api/V1/<Feature>Test.php` — its own vertical test file.
3. Validation: `pint --test`, `phpstan analyse`, `pest --filter=<Feature>`.
4. Add the endpoint checks to a per-feature script (copy `scripts/test-settings.ps1`).
5. Deploy once, then rely on the existing deploy/scaling/monitoring (no per-feature infra).