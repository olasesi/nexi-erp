# CRM Feature — Pilot Runbook (Leads · Pipeline Stages · Deals)

Use the **CRM feature** as another repeatable vertical slice: develop → test →
deploy → scale → monitor, observed **via the API alone**. Everything below works
with `curl` / PowerShell / `kubectl`.

The CRM slice covers the sales funnel under a "CRM" sidebar entry with three modules
matching the reference product's Smart Dashboard card:

- `crm-stages` — the ordered pipeline a company drags deals through. A stage with
  deals on it cannot be deleted (the FK is `restrictOnDelete` surfaced as a 422).
- `leads` — prospective customers with an acquisition `source`, a funnel `status`
  (`new → contacted → qualified → converted | lost`), an expected value, an owner,
  and a `next_follow_up_at`. `POST /api/v1/leads/{lead}/convert` promotes a lead into
  a real `contact` plus an open `deal` (idempotent: repeats reuse the contact/deal).
- `deals` — the pipeline rows: title, value, stage, status (`open | won | lost`),
  close date, linked lead/contact and owner.

Plus one dashboard tile feed, `GET /api/v1/dashboard/crm-summary`, returning the
funnel/pipeline numbers the Smart Dashboard card renders (active leads, pipeline
value, conversion rate, funnel by status, leads by source, deals per stage).

- Seeded permission modules: `leads`, `deals`, `crm-stages` (full CRUD each), all via
  `RolesAndPermissionsSeeder::$modules`.
- `leads.convert` maps to `leads.update` (promoting a lead is a mutation, not a new
  privilege) — added to `EnsurePermitted::ACTION_MAP`.
- Metrics published on `/api/metrics`: `nexi_erp_leads_total{status=...}`,
  `nexi_erp_deals_total{status=...}`, `nexi_erp_pipeline_value_open`.
- Webhook events: `lead.created/updated/deleted`, `deal.created/updated/deleted`,
  `crm_stage.created/updated/deleted`, plus the domain event `lead.converted`
  (subjects registered in `WebhookService::LIFECYCLE_SUBJECTS` / `DOMAIN_EVENTS`).

> Cache note: metrics counters live in the configured cache store (default `database`);
> counting gauges read the tables directly, so scrapes always reflect the current
> installed state, shared across PHP-FPM workers and pods.

---

## 0. Prerequisites

- PHP 8.2+, Composer (backend) · Docker or a Kubernetes cluster.
- Locally: `api/.env` with `APP_KEY`, `DB_CONNECTION=sqlite`, oauth keys generated.

---

## 1. Develop + test locally (fast loop, ~1 min)

```powershell
# api/
composer install
php artisan migrate:fresh --seed        # tables + demo data + leads/deals/crm-stages perms
php artisan passport:keys --force
php artisan passport:client --password --name="Nexi ERP Password Grant"
```

Copy the printed client id/secret into `api/.env` (`PASSPORT_PASSWORD_CLIENT_ID`,
`PASSPORT_PASSWORD_CLIENT_SECRET`), then:

```powershell
php artisan config:clear
php artisan serve                        # http://localhost:8000
```

Run the checks, then the end-to-end smoke test:

```powershell
# api/
php vendor\bin\pint --test
php -d memory_limit=1G vendor\bin\phpstan analyse --no-progress
php artisan test --filter='CrmStageTest|LeadTest|DealTest'

# repo root
.\scripts\test-crm.ps1                   # expects the server on :8000, cleans up after itself
```

Hand-roll a few calls to see the funnel work (role: admin):

```powershell
$t = (Invoke-RestMethod http://localhost:8000/api/v1/login -Method Post -ContentType application/json `
       -Body (@{email="admin@nexi-corp.com";password="password"} | ConvertTo-Json)).access_token
$h = @{ Authorization = "Bearer $t"; "Content-Type" = "application/json" }

$c = (Invoke-RestMethod http://localhost:8000/api/v1/companies -Headers $h).data[0].id

Invoke-RestMethod http://localhost:8000/api/v1/crm-stages -Method Post -Headers $h -Body (@{ company_id=$c; name="Qualified" } | ConvertTo-Json)
Invoke-RestMethod http://localhost:8000/api/v1/crm-stages -Method Post -Headers $h -Body (@{ company_id=$c; name="Negotiation" } | ConvertTo-Json)

$lead = (Invoke-RestMethod http://localhost:8000/api/v1/leads -Method Post -Headers $h -Body (@{ company_id=$c; name="Priya Raman"; organization="Raman Foods"; source="referral"; value=8500 } | ConvertTo-Json)).data
invoke-RestMethod ("http://localhost:8000/api/v1/leads/$($lead.id)/convert") -Method Post -Headers $h
Invoke-RestMethod http://localhost:8000/api/v1/deals -Headers $h
Invoke-RestMethod http://localhost:8000/api/v1/dashboard/crm-summary?company_id=$c -Headers $h

Invoke-WebRequest http://localhost:8000/api/metrics | Select-String "nexi_erp_(leads|deals|pipeline_value_open)"
```

---

## 2. Deploy the slice with Docker Compose

```powershell
# api/
docker compose up --build -d
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan passport:client --password --name="Nexi ERP Password Grant"
```

Put the client id/secret in the container `.env`, restart, and smoke through the
compose nginx:

```powershell
# api/
docker compose exec app php artisan config:clear
docker compose restart app
# repo root
.\scripts\test-crm.ps1 -BaseUrl http://localhost:80
.\scripts\load-test-crm.ps1 -BaseUrl http://localhost:80 -Concurrency 12 -RequestsPerWorker 40
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
.\scripts\test-crm.ps1 -BaseUrl http://localhost:8000
.\scripts\load-test-crm.ps1 -BaseUrl http://localhost:8000 -Concurrency 25 -RequestsPerWorker 50
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

CRM metrics on `/api/metrics` (gauges across the whole installation, like
`nexi_erp_users_total` — no auth, so the company scope is intentionally skipped):

```
nexi_erp_leads_total{status="new"|"contacted"|"qualified"|"converted"|"lost"}
nexi_erp_deals_total{status="open"|"won"|"lost"}
nexi_erp_pipeline_value_open      # sum(value) of open deals
```

Useful asks:
- `leads_total{status="converted"} / (converted + lost)` mirrors the dashboard's
  conversion rate; a sliding dip here means the funnel is staling.
- `pipeline_value_open` per deployment in Alertmanager for "no new pipeline value
  created" — early read on sales momentum before any invoice exists.
- `deals_total{status="open"}` growth against closed won: pipeline hygiene (stale
  open deals inflate the headline number without revenue).

---

## 5. Permissions & roles recap

- Seeded modules `leads`, `deals`, `crm-stages` → full CRUD permissions each
  (`view-any/view/create/update/delete`).
- `admin` has all; `manager` gets all except `*.delete`; the seeded `user` role does
  **not** get CRM by default — hand out `leads.view-any` etc. per team.
- Custom routes:
  - `leads.convert` → requires `leads.update` (promoting a lead is a mutation).
  - `crm-summary` dashboard feed → `dashboard.view-any`, same as the other cards.

## 6. Reusing this pattern for other features

Same backend-first recipe as the other slices — migration/model/factory/requests/
controller/resource/routes, per-feature tests, a `test-<feature>.ps1` and
`load-test-<feature>.ps1` pair, `MetricsService` gauges asserted in a feature test,
and the CRM dashboard card route if the module deserves a Smart Dashboard tile.
Cut this runbook down to the crate, then deploy once and rely on the existing
deploy/scale/monitor baseline.