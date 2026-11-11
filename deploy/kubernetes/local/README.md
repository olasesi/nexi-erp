# Local Kubernetes lab (Docker Desktop / kind / minikube)

Runs the whole Nexi ERP stack inside a local Kubernetes cluster - the same
shape as production, on your laptop:

```text
http://localhost (browser)
   │
   ▼
NGINX ingress controller          <- load balancer (host ports 80/443)
   ├── /api /storage /graphql ──► Service "web"  ──► web pods (nginx) ──► Service "app" ──► php-fpm pods ──► MySQL + Redis
   └── / ─────────────────────► Service "frontend" ──► SPA pods (nginx static)

   queue worker (Deployment)  +  scheduler (CronJob, every minute)
   nexi-erp-init (Job)        =  migrations + Passport keys, run once
```

The manifests mirror `docker-compose.prod.yml` service by service.

## 1. Build the images (from the repo root)

```powershell
docker build -t nexi-erp-api:lab api
docker build -t nexi-erp-web:lab -f api/Dockerfile.web api
docker build -t nexi-erp-frontend:lab .
```

`imagePullPolicy: IfNotPresent` lets the cluster use these local images. If
pods report `ErrImagePull`/`ImagePullBackOff`, enable "Use containerd for
pulling and storing images" in Docker Desktop settings, rebuild, and redeploy.

## 2. Configure secrets

Edit `01-app-config.yaml`:

- `APP_KEY` - generate:
  `docker run --rm php:8.2-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`
- `DB_PASSWORD` / `DB_ROOT_PASSWORD` - any local values.

## 3. Install the ingress controller (once per cluster)

```powershell
kubectl apply -f https://raw.githubusercontent.com/kubernetes/ingress-nginx/main/deploy/static/provider/cloud/deploy.yaml
kubectl wait --namespace ingress-nginx --for=condition=ready pod --selector=app.kubernetes.io/component=controller --timeout=300s
```

Port 80 must be free (stop XAMPP Apache and `docker compose ... down` any
compose stack first).

## 4. Deploy

```powershell
kubectl apply -f deploy/kubernetes/local/00-namespace.yaml
kubectl apply -f deploy/kubernetes/local/
kubectl -n nexi-erp get pods -w
```

The `nexi-erp-init` job runs migrations. Wait until it completes and all pods
are `Running/Ready`, then open http://localhost.

## 5. Create the Passport password client (login)

```powershell
kubectl -n nexi-erp exec deploy/app -- php artisan passport:client --password --name="Nexi ERP Password Grant"
kubectl -n nexi-erp patch secret nexi-erp-secrets -p '{"stringData":{"PASSPORT_PASSWORD_CLIENT_ID":"<id>","PASSPORT_PASSWORD_CLIENT_SECRET":"<secret>"}}'
kubectl -n nexi-erp rollout restart deployment/app deployment/queue
```

(fill in the printed id/secret). Then seed and log in with
`admin@nexi-corp.com` / `password`:

```powershell
kubectl -n nexi-erp exec deploy/app -- php artisan db:seed --force
```

## Day-2 operations

| Task | Command |
|---|---|
| Logs | `kubectl -n nexi-erp logs deploy/app -f` / `deploy/web` / `deploy/queue` |
| Re-run migrations after code changes | rebuild image, then `kubectl -n nexi-erp delete job nexi-erp-init` and re-apply the folder |
| Rolling restart | `kubectl -n nexi-erp rollout restart deployment/app deployment/web deployment/frontend` |
| Scale | `kubectl -n nexi-erp scale deployment/frontend --replicas=4` |
| Shell into a pod | `kubectl -n nexi-erp exec deploy/app -it -- sh` |
| Teardown | `kubectl delete namespace nexi-erp` (removes everything) |
