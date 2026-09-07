# Nexi ERP

Monorepo with a **React (Vite)** frontend and a **Laravel 12** API backend.

```
nexi-erp/
├── api/            # Laravel 12 API (PHP 8.2+, Sanctum, GraphQL)
├── src/            # React SPA (Vite, TypeScript, Tailwind v4, React Router)
│   ├── components/ # Shared UI (Layout, Table, ErrorBoundary)
│   ├── pages/      # Route pages (Dashboard, Companies, Contacts, etc.)
│   ├── lib/        # API client, hooks, utilities
│   └── types/      # TypeScript types matching Laravel resources
├── e2e/            # Playwright end-to-end tests
├── deploy/
│   └── kubernetes/ # K8s Deployment, Service, Ingress
├── Dockerfile      # Multi-stage build → static Nginx serve
├── Jenkinsfile     # CI/CD pipeline
└── ...
```

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

### Frontend

```bash
npm install
npm run dev                # runs on http://localhost:3000
```

The Vite dev server proxies `/api/*` requests to `http://localhost:8000`.

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
- **Tailwind CSS v4** - Utility-first styling
- **ESLint 9** (flat config) + **Prettier** - Code quality & formatting
- **Vitest** + **Testing Library** - Unit/integration tests
- **Playwright** - E2E tests
- **MSW** - API mocking for tests
- **Husky** + **lint-staged** + **commitlint** - Pre-commit checks
- **Loglevel** - Client-side logging
- **Web Vitals** - Performance monitoring

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

| Variable        | Default                  | Description                        |
| --------------- | ------------------------ | ---------------------------------- |
| `VITE_API_URL`  | `http://localhost:8000`  | Laravel API base URL (build-time)  |
