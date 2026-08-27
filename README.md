# Nexi ERP

Monorepo with a **Next.js 15** frontend and a **Laravel 12** API backend, packaged for production with Docker and Kubernetes (auto-scaling), CI/CD, error tracking, and generated API documentation.

```
nexi-erp/
├── api/                 # Laravel 12 API (PHP 8.2+, Sanctum, Scribe, Lighthouse/GraphQL)
│   ├── Dockerfile       # PHP-FPM image
│   ├── docker-compose.yml
│   ├── deploy/kubernetes/  # Deployment, Service, Ingress, HPA
│   └── Jenkinsfile      # Backend CI/CD pipeline
├── src/                 # Next.js 15 frontend (App Router, TypeScript, Tailwind v4)
├── e2e/                 # Playwright end-to-end tests
├── deploy/kubernetes/   # Frontend Deployment, Service, Ingress, HPA
├── Dockerfile           # Multi-stage Next.js standalone build
├── Jenkinsfile          # Frontend CI/CD pipeline
└── ...
```

## Architecture

- **Frontend**: Next.js 15 (App Router) + React 19 + TypeScript + Tailwind CSS v4.
- **Backend**: Laravel 12 (PHP 8.2) using API resources, Sanctum auth, Spatie permissions, and Lighthouse (GraphQL).
- The Next.js dev server proxies `/api/*` to `http://localhost:8000` (see `next.config.ts`).

## Quick Start

### Backend (API)

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve          # http://localhost:8000
```

Alternative with Docker:

```bash
cd api
docker compose up -d
```

### Frontend

```bash
npm install
npm run dev                # http://localhost:3000
```

## Testing

| Layer     | Frontend                 | Backend              |
| --------- | ------------------------ | -------------------- |
| Unit      | Vitest + Testing Library | Pest 3 / PHPUnit     |
| E2E       | Playwright (`e2e/`)      | —                    |
| Static    | ESLint + Prettier + tsc  | Pint + PHPStan(L8)   |
| Coverage  | Vitest v8 coverage       | PHPUnit coverage     |

```bash
# Frontend
npm run test              # Vitest unit tests
npm run test:e2e          # Playwright E2E
npm run validate          # typecheck + lint + format + tests

# Backend (from api/)
composer test             # Pest/PHPUnit
vendor/bin/pint --test    # code style
vendor/bin/phpstan analyse --no-progress   # static analysis
```

The backend PHPStan baseline (`api/phpstan-baseline.neon`) captures pre-existing
analysis debt so the CI stays green while still blocking new errors.

## Logging

- **Frontend**: `loglevel` client-side logger (`src/lib/logger.ts`), configurable via
  `NEXT_PUBLIC_LOG_LEVEL` (defaults to `debug` in dev, `warn` in production). Integrated
  into the API client and the global error boundary.
- **Backend**: Laravel Monolog channels in `config/logging.php` (`stack`, `daily`, `api`,
  `sql`, `audit`, plus a `sentry` channel). Realtime dev logs via `laravel/pail`.

## Documentation

- **API**: [Scribe](https://scribe.knuckles.wtf/laravel) generates interactive docs,
  OpenAPI spec, and Postman collection: `php artisan scribe:generate` →
  `http://localhost:8000/docs`.
- **Frontend**: this README plus JSDoc-style comments where applicable.

## Monitoring & Error Tracking

- **Frontend**: Sentry (`@sentry/nextjs`) via `src/sentry.client.config.ts` /
  `src/sentry.server.config.ts` and `src/instrumentation.ts`. Reads `SENTRY_DSN`; disabled
  when unset. The app-level error boundary (`src/app/error.tsx`) captures exceptions.
- **Backend**: Sentry (`sentry/sentry-laravel`) wired through
  `config/sentry.php` and `bootstrap/app.php` (exception handler). Uses
  `SENTRY_LARAVEL_DSN` / `SENTRY_DSN`; disabled when unset.
- **Health endpoints**: both Kubernetes probes hit a `/health` route (backend) that reports
  DB, cache and storage status.

## Deployment

- **Frontend**: multi-stage standalone `Dockerfile` (Node 20 Alpine), deployed via the
  root `Jenkinsfile` to Kubernetes.
- **Backend**: `api/Dockerfile` (PHP-FPM), `api/docker-compose.yml` (app, nginx, MySQL,
  Redis, queue, scheduler), deployed via `api/Jenkinsfile` to Kubernetes.

Kubernetes manifests live in `deploy/kubernetes/` (frontend) and
`api/deploy/kubernetes/` (backend): Deployment, Service, Ingress, and HorizontalPodAutoscaler.

## Scaling

Both services are auto-scaled with Kubernetes **HorizontalPodAutoscaler** (HPA) based on
CPU (70%) and memory (80%) utilization:

- Frontend `deploy/kubernetes/hpa.yaml`: 2 → 8 replicas.
- Backend `api/deploy/kubernetes/hpa.yaml`: 3 → 10 replicas.

HPA requires a [metrics-server](https://github.com/kubernetes-sigs/metrics-server) in the cluster.

## CI/CD

- `Jenkinsfile` (frontend): typecheck → ESLint → Prettier → Vitest → build → Playwright →
  Docker image → Kubectl deploy, with JUnit/coverage reporting and Slack notifications.
- `api/Jenkinsfile` (backend): composer install → Pint → PHPStan → Pest/PHPUnit with JUnit →
  Scribe docs → Docker image → Kubectl deploy, with Slack notifications.

## Environment Variables

Frontend:

| Variable                | Default                   | Description                 |
| ----------------------- | ------------------------- | --------------------------- |
| `NEXT_PUBLIC_API_URL`   | `http://localhost:8000`   | Laravel API base URL        |
| `NEXT_PUBLIC_LOG_LEVEL` | (dev/debug, prod/warn)    | Client-side log verbosity   |
| `SENTRY_DSN`            | *(optional)*              | Frontend error tracking DSN |

Backend (see `api/.env.example` for the full list):

| Variable            | Default | Description                            |
| ------------------- | ------- | -------------------------------------- |
| `SENTRY_LARAVEL_DSN`| *(empty)* | Backend error tracking DSN           |
| `LOG_CHANNEL`       | `stack` | Default log channel                    |
| `LOG_LEVEL`         | `debug` | Backend log verbosity                  |

## Tooling

- **TypeScript** — strict mode
- **ESLint 9** — flat config, React hooks, import ordering
- **Prettier** — Tailwind class sorting
- **Husky + lint-staged + commitlint** — pre-commit checks & conventional commits
- **Vitest + Testing Library + MSW + Playwright** — frontend testing
- **Pest/PHPUnit + Pint + Larastan (PHPStan)** — backend testing & analysis
- **Docker + Docker Compose + Kubernetes + HPA** — containerization & scaling
- **Jenkins** — CI/CD for both services
- **Sentry** — error tracking & performance (frontend & backend)
- **Scribe** — API documentation
