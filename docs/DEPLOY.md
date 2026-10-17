# Deploying Nexi ERP

Nexi ERP is a monorepo: a **React (Vite) SPA** in `src/` and a **Laravel 12 API**
in `api/`. Whatever the host, two things must run:

- the built SPA (`dist/`) served as static files, and
- the Laravel API with **PHP 8.2+**, a **MySQL** database, a **queue worker**
  and the **scheduler**.

The API also uses **Redis** for cache/queue/sessions in production (optional but
recommended) and a writable `api/storage` directory for uploads and logs.

---

## Option A - Docker Compose (recommended)

The repo ships a complete production stack in `docker-compose.prod.yml`:
SPA + Nginx ingress, PHP-FPM, queue worker, scheduler, MySQL and Redis - one
`up` away. This is the most portable option and works on any VPS, on any cloud
or on a local machine.

### 1. Prepare the environment

```bash
cp api/.env.production.example api/.env.production
```

Edit `api/.env.production` and set at minimum:

- `APP_KEY` - generate with `php artisan key:generate --show` (or `docker run --rm -it php:8.2-cli php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`)
- `APP_URL` and `FRONTEND_URL` - the public address users will browse
- `DB_PASSWORD`, `DB_ROOT_PASSWORD`

### 2. Build and start

```bash
docker compose --env-file api/.env.production -f docker-compose.prod.yml build
docker compose --env-file api/.env.production -f docker-compose.prod.yml up -d
```

The first boot runs a one-shot `init` container that migrates the schema,
generates Passport keys and links storage. The SPA is published on
`http://<host>:${HTTP_PORT:-80}`.

> Tip: to avoid repeating the long `-f`/`--env-file` flags, add a shell alias:
> `alias nexi='docker compose --env-file api/.env.production -f docker-compose.prod.yml'`

### 3. Create the OAuth password client (once)

```bash
docker compose --env-file api/.env.production -f docker-compose.prod.yml exec app \
  php artisan passport:client --password --name="Nexi ERP Password Grant"
```

Copy the printed **Client ID** and **Secret** into
`api/.env.production` (`PASSPORT_PASSWORD_CLIENT_ID` /
`PASSPORT_PASSWORD_CLIENT_SECRET`), then restart the app containers:

```bash
docker compose --env-file api/.env.production -f docker-compose.prod.yml up -d
```

### 4. Seed the first admin (optional)

```bash
docker compose --env-file api/.env.production -f docker-compose.prod.yml exec app \
  php artisan db:seed --force
```

### Day-2 operations

| Task | Command |
|---|---|
| Update after `git pull` | `docker compose ... build && docker compose ... up -d` |
| Run a migration | `docker compose ... exec app php artisan migrate --force` |
| Open a shell | `docker compose ... exec app sh` |
| Follow logs | `docker compose ... logs -f web queue scheduler` |
| Backup the database | `docker compose ... exec db mysqldump -uroot -p"$DB_ROOT_PASSWORD" nexi_erp > backup.sql` |

Put a reverse proxy with TLS (Caddy, Nginx, Traefik) in front of the published
port and set `APP_URL`/`FRONTEND_URL` to the HTTPS URL. Terminate TLS at the
proxy and forward `X-Forwarded-Proto` - Laravel honors it via
`TrustProxies` when configured.

---

## Option B - cPanel / shared hosting

See **[DEPLOY-cpanel.md](DEPLOY-cpanel.md)** for a step-by-step guide (document
root, `.htaccess`, cron-driven scheduler and queue, building the SPA).

---

## Option C - other platforms

Because the app is a standard Laravel project plus a static SPA, it runs on:

- **Laravel-native**: Laravel Cloud, Laravel Forge (+ any VPS), Ploi, WashData,
  RunCloud, Cloudways, Laravel Vapor (serverless on AWS).
- **Container PaaS**: Render, Railway, Fly.io, Koyeb, Northflank,
  DigitalOcean App Platform, Platform.sh/Upsun, Heroku.
- **Self-hosted PaaS**: Coolify, Dokploy, CapRover, Dokku (install once on a
  VPS, then deploy like Heroku).
- **Cloud IaaS**: AWS (EC2/ECS + RDS + ElastiCache + S3), Google Cloud
  (Compute Engine/Cloud Run), Azure (App Service/Container Apps),
  DigitalOcean, Hetzner, Vultr, Linode.
- **Static SPA only** (API hosted elsewhere): Netlify, Vercel, Cloudflare Pages,
  GitHub Pages, S3 + CloudFront. Build with
  `VITE_API_URL=https://api.example.com npm run build`.

Whichever you pick, the requirements are the same: PHP 8.2+, MySQL, a queue
worker, the scheduler, writable `storage/`, and Redis if you enable it.
