# Auth Feature (Customer Portal) — Pilot Runbook

Use the **Auth feature** as a repeatable vertical slice: develop → test → deploy →
scale → monitor, observed **via the API alone** (no frontend needed). Everything
below works with `curl` / PowerShell / `kubectl`.

The Auth slice covers: staff login/logout, staff registration, the **customer
portal** (register, `me`, contact-scoped documents), role separation (403s),
and dedicated Prometheus counters for login successes, failures and registrations.

> Cache note: the API's cache store defaults to `database` (see `api/config/cache.php`).
> That means `/api/metrics` counters — including the new `nexi_erp_auth_*` ones — are
> shared across PHP-FPM workers and pods. No Redis required.

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
php artisan test --filter=Auth           # AuthControllerTest + CustomerLoginTest + AuthMetricsTest

# repo root
.\scripts\test-auth.ps1                  # expects the server on :8000, seeds a temp customer contact
```

Hand-roll a few calls to see behavior:

```powershell
$t = (Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t" }
Invoke-RestMethod http://localhost:8000/api/v1/user -Headers $h                                       # profile + company
Invoke-RestMethod http://localhost:8000/api/metrics | Select-String "nexi_erp_auth_"                  # auth counters
Invoke-RestMethod http://localhost:8000/api/health                                                    # no auth needed
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
.\scripts\test-auth.ps1 -BaseUrl http://localhost:80
.\scripts\load-test-auth.ps1 -BaseUrl http://localhost:80 -Concurrency 15 -RequestsPerWorker 40
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

kubectl create secret generic nexi-erp-db --from-literal=host=... --from-literal=database=... --from-literal=username=... --from-literal=password=...
kubectl create secret generic nexi-erp-app --from-literal=key=<APP_KEY> --from-literal=oauth_password_client_id=<id> --from-literal=oauth_password_client_secret=<secret> --from-literal=sentry_dsn=

kubectl apply -f api/deploy/kubernetes/nginx-config.yaml
kubectl apply -f api/deploy/kubernetes/deployment.yaml   # reads the oauth_* secret keys above as PASSPORT_PASSWORD_CLIENT_ID/SECRET
kubectl apply -f api/deploy/kubernetes/hpa.yaml
```

Seed + create the OAuth password client exactly once (do this **before** scaling up,
or the password grant will fail for every pod until the client exists):

```powershell
kubectl exec deploy/nexi-erp -- php artisan migrate:fresh --seed
kubectl exec deploy/nexi-erp -- php artisan passport:client --password --name="Nexi ERP Password Grant"
kubectl create secret generic nexi-erp-app --from-literal=oauth_password_client_id=<id> --from-literal=oauth_password_client_secret=<secret> --dry-run=client -o yaml | kubectl apply -f -
kubectl rollout restart deploy/nexi-erp
```

Expose locally and run the whole loop against the **deployed** API:

```powershell
kubectl port-forward svc/nexi-erp 8000:80
.\scripts\test-auth.ps1 -BaseUrl http://localhost:8000
.\scripts\load-test-auth.ps1 -BaseUrl http://localhost:8000 -Concurrency 30 -RequestsPerWorker 50
```

**Watch it scale** (new terminal):

```powershell
kubectl get hpa nexi-erp -w            # 3 -> 10 replicas once CPU crosses 70%
kubectl get pods -l app=nexi-erp -w
kubectl top pods -l app=nexi-erp       # CPU/memory per pod
```

> HPA uses CPU/memory resource metrics (metrics-server), independent of `/api/metrics`.
> Because each pod's counters share the database cache store, you can also watch the
> aggregate `nexi_erp_auth_logins_total` counter climb as pods are added while
> `load-test-auth.ps1` is running — a log-in on pod A shows up in the metrics surfaced
> by pod B.

---

## 4. Monitoring

| What | Where | Response |
|---|---|---|
| App + DB + cache + storage health | `GET /api/health` | JSON `{status, checks}` |
| Liveness (deployment) | `GET /up` | 200 plaintext `ok` |
| Prometheus metrics | `GET /api/metrics` | Prometheus text (see below) |
| Errors to Sentry | exceptions via Sentry | needs DSN |
| Laravel logs | `api/storage/logs/laravel.log` | lines per request/error |

Auth-specific counters on `/api/metrics` (values shared via the cache store):

```
nexi_erp_auth_logins_total            # counter, successful password-grant logins
nexi_erp_auth_login_failures_total    # counter, rejected password-grant logins (wrong creds / misconfig)
nexi_erp_auth_registrations_total     # counter, staff + customer portal accounts created
```

Useful asks:
- `(logins_total - login_failures_total) / logins_total` → the auth success rate.
- A sudden rise in `login_failures_total` with no matching `logins_total` usually means
  a leaked credential being brute-forced, or the OAuth client misconfigured on one pod.

The manifests carry `prometheus.io/scrape=true`, `port=80`, `path=/api/metrics` on the
Service and pods, so a prometheus-operator/kube-prometheus stack picks them up without
extra config. Without Prometheus you can still watch:

```powershell
kubectl port-forward svc/nexi-erp 9000:80
Invoke-WebRequest http://localhost:9000/api/metrics | Select-Object -ExpandProperty Content
```

---

## 5. Reusing this pattern for other features

Each new feature ships the same recipe, backend-first:

1. Migration + Model + Factory + FormRequest + Controller + Resource + routes.
2. `api/tests/Feature/Api/V1/<Feature>Test.php` — its own vertical test file.
3. Validation: `pint --test`, `phpstan analyse`, `artisan test --filter=<Feature>`.
4. Add the endpoint checks to a per-feature script (copy `scripts/test-auth.ps1`).
5. If the feature is observable, expose a counter from `MetricsService` and assert it
   in a `tests/Feature/Api/<Feature>MetricsTest.php` (see `AuthMetricsTest`).
6. Deploy once, then rely on the existing deploy/scaling/monitoring (no per-feature infra).