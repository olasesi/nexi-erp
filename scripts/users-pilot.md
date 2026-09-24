# User Management Feature — Pilot Runbook

Use the **User Management feature** as a repeatable vertical slice: develop → test →
deploy → scale → monitor, observed **via the API alone** (no frontend needed).
Everything below works with `curl` / PowerShell / `kubectl`.

The User Management slice covers: **users** (list/search/filter, create with roles,
show, update incl. password + enable/disable, delete with self-delete and last-admin
guards) and **roles** (list, create with permissions, show, update, delete with
admin/in-use guards), both gated by the seeded `users.*` / `roles.*` permissions,
plus a `nexi_erp_users_total` gauge on `/api/metrics`.

> Cache note: the API's cache store defaults to `database` (see `api/config/cache.php`).
> That means `/api/metrics` counters are shared across PHP-FPM workers and pods.
> No Redis required.

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
php artisan migrate:fresh --seed        # tables + admin/manager/cashier users + demo data + users./roles. perms
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
php artisan test --filter='UserManagementTest|RoleManagementTest'

# repo root
.\scripts\test-users.ps1                 # expects the server on :8000, cleans up after itself
```

Hand-roll a few calls to see behavior:

```powershell
$t = (Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t" }
Invoke-RestMethod http://localhost:8000/api/v1/users -Headers $h                       # paginated users w/ roles
Invoke-RestMethod http://localhost:8000/api/v1/users?search=cashier -Headers $h       # search
Invoke-RestMethod http://localhost:8000/api/v1/users?is_active=0 -Headers $h          # filter: disabled
Invoke-RestMethod http://localhost:8000/api/v1/users?role=manager -Headers $h         # filter: by role
Invoke-RestMethod "http://localhost:8000/api/v1/roles" -Headers $h                    # roles w/ users_count + permissions
Invoke-WebRequest http://localhost:8000/api/metrics | Select-String "nexi_erp_users_total"
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
.\scripts\test-users.ps1 -BaseUrl http://localhost:80
.\scripts\load-test-users.ps1 -BaseUrl http://localhost:80 -Concurrency 12 -RequestsPerWorker 40
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
or the password grant will fail for every pod until the client exists). The seeder
creates the `users.*` / `roles.*` permissions, so `EnsurePermitted` gates the two new
resource routes from the start:

```powershell
kubectl exec deploy/nexi-erp -- php artisan migrate:fresh --seed
kubectl exec deploy/nexi-erp -- php artisan passport:client --password --name="Nexi ERP Password Grant"
kubectl create secret generic nexi-erp-app --from-literal=oauth_password_client_id=<id> --from-literal=oauth_password_client_secret=<secret> --dry-run=client -o yaml | kubectl apply -f -
kubectl rollout restart deploy/nexi-erp
```

Expose locally and run the whole loop against the **deployed** API:

```powershell
kubectl port-forward svc/nexi-erp 8000:80
.\scripts\test-users.ps1 -BaseUrl http://localhost:8000
.\scripts\load-test-users.ps1 -BaseUrl http://localhost:8000 -Concurrency 25 -RequestsPerWorker 50
```

**Watch it scale** (new terminal):

```powershell
kubectl get hpa nexi-erp -w            # 3 -> 10 replicas once CPU crosses 70%
kubectl get pods -l app=nexi-erp -w
kubectl top pods -l app=nexi-erp       # CPU/memory per pod
```

> HPA uses CPU/memory resource metrics (metrics-server), independent of `/api/metrics`.
> Because each pod's counter/gauge values compute against the shared database, the
> `nexi_erp_users_total` gauge stays consistent no matter which pod answers.

---

## 4. Monitoring

| What | Where | Response |
|---|---|---|
| App + DB + cache + storage health | `GET /api/health` | JSON `{status, checks}` |
| Liveness (deployment) | `GET /up` | 200 plaintext `ok` |
| Prometheus metrics | `GET /api/metrics` | Prometheus text (see below) |
| Errors to Sentry | exceptions via Sentry | needs DSN |
| Laravel logs | `api/storage/logs/laravel.log` | lines per request/error |

User-management gauge on `/api/metrics`:

```
nexi_erp_users_total{status="active"}     # gauge, active (is_active=1) user accounts
nexi_erp_users_total{status="inactive"}   # gauge, disabled user accounts
```

Useful asks:
- `inactive / (active + inactive)` → the disabled-account ratio; a spike during
  offboarding is expected, a gradual climb can mean stale accounts.
- Delta of active users versus `nexi_erp_auth_registrations_total` → reconciling
  auto-registered customers vs. manually managed staff accounts.

The manifests carry `prometheus.io/scrape=true`, `port=80`, `path=/api/metrics` on the
Service and pods, so a prometheus-operator/kube-prometheus stack picks them up without
extra config. Without Prometheus you can still watch:

```powershell
kubectl port-forward svc/nexi-erp 9000:80
Invoke-WebRequest http://localhost:9000/api/metrics | Select-Object -ExpandProperty Content
```

---

## 5. Permissions & roles recap

- Seeded modules now include `users` and `roles` → permissions `users.view-any/view/create/update/delete`
  and `roles.view-any/view/create/update/delete`.
- `admin` has all of them; `manager` has all except `*.delete` (can manage users and
  roles but not delete them); the seeded `user` role has none (read-only staff area or
  front-end only).
- The routes report **403** (`You do not have permission to perform this action.`)
  when the caller lacks the derived permission — verify with `scripts/test-users.ps1`.

## 6. Reusing this pattern for other features

Each new feature ships the same recipe, backend-first:

1. Migration + Model + Factory + FormRequest + Controller + Resource + routes.
2. `api/tests/Feature/Api/V1/<Feature>Test.php` — its own vertical test file.
3. Validation: `pint --test`, `phpstan analyse`, `artisan test --filter=<Feature>`.
4. Add the endpoint checks to a per-feature script (copy `scripts/test-users.ps1`).
5. If the feature is observable, expose a counter/gauge from `MetricsService` and assert
   it in a feature test (mirror `UserManagementTest` + the `nexi_erp_users_total` gauge).
6. Deploy once, then rely on the existing deploy/scaling/monitoring (no per-feature infra).