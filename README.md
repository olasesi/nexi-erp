# Nexi ERP

Monorepo with a **React (Vite)** frontend and a **Laravel 12** API backend.

```
nexi-erp/
├── api/            # Laravel 12 API (PHP 8.2+, Passport OAuth2)
├── src/            # React SPA (Vite, TypeScript, React Router, BEM CSS)
│   ├── components/ # App shell, auth guard, charts and UI primitives
│   ├── features/   # Analytics views (executive, financial, sales, operations)
│   ├── pages/      # Route pages (Dashboard, Reports, Companies, Contacts, …)
│   ├── styles/     # BEM stylesheet (tokens, layout, blocks, pages)
│   ├── lib/        # API client, auth store, query config, logging, monitoring
│   ├── __tests__/  # Page-level integration tests
│   └── types/      # TypeScript types matching Laravel resources
├── e2e/            # Playwright end-to-end tests
├── deploy/
│   └── kubernetes/ # K8s Deployment, Service, Ingress
├── Dockerfile      # Multi-stage build → static Nginx serve
├── Jenkinsfile     # CI/CD pipeline
└── ...
```

The backend has its own documentation: [api/README.md](api/README.md) covers
authentication, permissions, add-ons, imports/exports, webhooks, the console
commands, API versioning and the health/metrics endpoints.

## Quick Start

### Backend (API)

```bash
cd api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan passport:keys
php artisan passport:client --password --name="Nexi ERP Password Grant"
php artisan db:seed --force
php artisan serve          # runs on http://localhost:8000
```

Copy the password client's `Client ID` / `Client secret` from the previous
step into `.env` as `PASSPORT_PASSWORD_CLIENT_ID` / `PASSPORT_PASSWORD_CLIENT_SECRET`.

> **Auth:** The API uses **Laravel Passport (OAuth2)** with the password grant.
> `POST /api/v1/login` and `POST /api/v1/register` exchange credentials for an
> `access_token` / `refresh_token`; `GET /api/v1/user` returns the current
> authenticated user; `POST /api/v1/logout` revokes the access token. All
> `/api/v1/*` resource routes require `Authorization: Bearer <token>`.

The API also ships opt-in vertical add-ons — **Coworking Space**, **Laundry &
Dry Cleaning**, **Gym Management** and **Rental Management** — each a
self-contained bundle of storage, lifecycle rules and permissions toggled per
company. See [api/README.md](api/README.md#vertical-add-ons) for their routes
and domain invariants.

Webhook deliveries, the retry/prune commands and the API version catalogue are
covered in [api/README.md](api/README.md). With `WEBHOOK_DRIVER=sync` (the
default) the API delivers webhooks in-process; run `php artisan
schedule:run` every minute so `webhooks:retry` and `webhooks:prune` keep the
delivery log moving. Production runs `WEBHOOK_DRIVER=queue`, which needs both a
queue worker and the scheduler — the Kubernetes manifests ship both
(`deploy/kubernetes/deployment.yaml` has a `queue` container, `cronjob.yaml`
runs the scheduler every minute), and `docker-compose.yml` has the matching
`queue` and `scheduler` services.

### Frontend

```bash
npm install
npm run dev                # runs on http://localhost:3000
```

The Vite dev server proxies `/api/*` to `http://localhost:8000`, so the SPA
talks to the API through the same origin in development and production.

### What the SPA covers

- **Authentication** — Passport password grant, token persistence, session
  bootstrap, route guard and a central 401 sign-out.
- **Dashboard** — company KPIs, financial-year sales trend, payment-due feeds,
  stock alerts, pending shipments, CRM pipeline, project delivery and today's
  POS activity, all from `/api/v1/dashboard*`.
- **Reports** — profit & loss, balance sheet, cash flow, VAT summary and sales
  by product/category, with CSV export (`/api/v1/reports/*`).
- **Analytics** — executive, financial, sales and operations tabs, business
  health, receivables aging and the opt-in vertical add-ons.
- **Operations** — companies, contacts, products, warehouses, sales orders,
  purchase orders and settings.

Every route is code-split with `React.lazy`, all HTTP goes through
`src/lib/api.ts`, and every page renders from the shared BEM component set in
`src/styles/` (no utility CSS framework).

## Scripts

| Command                | Description                              |
| ---------------------- | ---------------------------------------- |
| `npm run dev`          | Start Vite dev server                    |
| `npm run build`        | Type-check + build static bundle to dist |
| `npm run preview`      | Serve built bundle locally               |
| `npm run lint`         | Run ESLint                               |
| `npm run lint:fix`     | Run ESLint with auto-fix                 |
| `npm run format`       | Format code with Prettier                |
| `npm run format:check` | Check formatting without writing         |
| `npm run typecheck`    | TypeScript type checking                 |
| `npm run test`         | Run unit tests (Vitest)                  |
| `npm run test:watch`   | Run tests in watch mode                  |
| `npm run test:coverage`| Run tests with coverage report           |
| `npm run test:e2e`     | Run Playwright end-to-end tests          |
| `npm run validate`     | typecheck + lint + format + test         |

## Tooling

- **Vite 6** + **React 19** - Fast build & HMR
- **React Router 7** - Client-side routing (SPA)
- **TypeScript** - Strict mode
- **BEM CSS** - Hand-written design system (`src/styles/`), no CSS framework
- **TanStack Query** - Request caching, retries and stale-while-revalidate
- **ESLint 9** (flat config) + **Prettier** - Code quality & formatting
- **Vitest** + **Testing Library** - Unit/integration tests
- **Playwright** - E2E tests
- **Loglevel** - Client-side logging
- **Sentry** + **Web Vitals** - Error and performance monitoring

## Deployment (Static)

The build produces a **static SPA** in `dist/` — no Node server required. Serve it with any static host (Nginx, S3, CDN) and configure SPA fallback to `index.html`.

### Docker

```bash
# Build (outputs an Nginx static image)
docker build -t nexi-erp-frontend .

# Run
docker run -p 8080:80 nexi-erp-frontend
```

### Environment Variables

| Variable           | Default         | Description                                             |
| ------------------ | --------------- | ------------------------------------------------------- |
| `VITE_API_URL`     | empty           | API origin baked in at build time; empty = same origin  |
| `VITE_SENTRY_DSN`  | unset           | Enables Sentry error and performance reporting          |
| `VITE_LOG_LEVEL`   | `warn` (`debug` in dev) | `trace`/`debug`/`info`/`warn`/`error`           |
| `VITE_APP_VERSION` | package version | Sentry release name                                      |

Copy `.env.example` to `.env.local` for development. All `VITE_*` values are
**build-time**: nothing is read at runtime, so changing them requires a rebuild.
Leave `VITE_API_URL` empty for a same-origin deploy (the bundled Nginx proxies
`/api`, `/graphql` and `/storage` to the backend — see
`deploy/docker/nginx/frontend.conf`) or pass a build arg:

```bash
docker build --build-arg VITE_API_URL=https://api.example.com -t nexi-erp-frontend .
```

## Monitoring & operations

- **Errors** — `initMonitoring()` (called from `src/main.tsx`) starts Sentry when
  `VITE_SENTRY_DSN` is set; `ErrorBoundary` and the API layer forward failures
  with `captureException()`. Console logging stays local at `VITE_LOG_LEVEL`.
- **Performance** — `reportWebVitals()` streams CLS/INP/LCP/TTFB/FCP to Sentry as
  measurements and breadcrumbs; browser tracing runs at 10% in production.
- **Request health** — TanStack Query caches dashboard/report reads for 30s,
  5-minute garbage collection, two retries for 5xx/network errors and none for
  4xx, so a flaky API degrades gracefully instead of hammering it.
- **Scaling** — the deployable is a static bundle: cache hashed `/assets/*`
  immutably, serve `index.html` with `no-store`, and scale replicas/CDN. The
  Kubernetes manifests (`deploy/kubernetes/`) ship 2 replicas, rolling updates
  and a CPU/memory HPA up to 8 pods.
- **Bundle budget** — vendor chunks are split by group (React, charts, query,
  Sentry) and routes are lazy-loaded; run `ANALYZE=1 npm run build` to produce
  `stats.html`.

## Deploying

Full deployment guides live in [`docs/`](docs/DEPLOY.md):

- **Docker Compose (recommended, any host)** - `docker-compose.prod.yml` brings
  up the SPA, PHP-FPM, queue worker, scheduler, MySQL and Redis in one command.
  See [docs/DEPLOY.md](docs/DEPLOY.md#option-a---docker-compose-recommended).
- **cPanel / shared hosting** - step-by-step for document root, cron-driven
  scheduler and queue, and building the SPA:
  [docs/DEPLOY-cpanel.md](docs/DEPLOY-cpanel.md).
- **Other platforms** (Laravel Cloud/Forge, Render, Railway, Fly.io, Coolify,
  AWS, ...): [docs/DEPLOY.md](docs/DEPLOY.md#option-c---other-platforms).
