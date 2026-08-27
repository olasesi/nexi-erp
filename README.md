# Nexi ERP

Monorepo with a **Next.js 15** frontend and a **Laravel 12** API backend.

```
nexi-erp/
├── api/            # Laravel 12 API (PHP 8.2+, Sanctum, GraphQL)
├── src/            # Next.js 15 frontend (App Router, TypeScript, Tailwind v4)
├── e2e/            # Playwright end-to-end tests
├── deploy/
│   └── kubernetes/ # K8s Deployment, Service, Ingress
├── Dockerfile      # Multi-stage production build
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
php artisan serve          # runs on http://localhost:8000
```

### Frontend

```bash
npm install
npm run dev                # runs on http://localhost:3000
```

The Next.js dev server proxies `/api/*` requests to `http://localhost:8000`.

## Scripts

| Command                | Description                              |
| ---------------------- | ---------------------------------------- |
| `npm run dev`          | Start Next.js dev server                 |
| `npm run build`        | Build for production                     |
| `npm run start`        | Start production server                  |
| `npm run lint`         | Run ESLint                               |
| `npm run lint:fix`     | Run ESLint with auto-fix                 |
| `npm run format`       | Format code with Prettier                |
| `npm run format:check` | Check formatting without writing         |
| `npm run typecheck`    | TypeScript type checking                 |
| `npm run test`         | Run unit tests (Vitest)                  |
| `npm run test:watch`   | Run tests in watch mode                  |
| `npm run test:coverage`| Run tests with coverage report           |
| `npm run test:e2e`     | Run Playwright end-to-end tests          |
| `npm run validate`     | Run typecheck + lint + format + tests    |

## Tooling

- **TypeScript** - Strict mode enabled
- **ESLint 9** - Flat config with React hooks, import ordering
- **Prettier** - With Tailwind CSS class sorting
- **Vitest** + **Testing Library** - Unit/integration tests
- **Playwright** - Cross-browser E2E tests (Chrome, Firefox, Safari)
- **MSW** - API mocking for tests
- **Husky** + **lint-staged** + **commitlint** - Pre-commit checks & conventional commits
- **Loglevel** - Client-side logging
- **Web Vitals** - Performance monitoring
- **Docker** - Multi-stage production build (Node 20 Alpine)
- **Jenkins** - CI/CD pipeline (lint, test, build, deploy)
- **Kubernetes** - Deployment, Service, Ingress manifests

## Docker

```bash
# Build
docker build -t nexi-erp-frontend .

# Run
docker run -p 3000:3000 nexi-erp-frontend
```

## Environment Variables

| Variable              | Default                   | Description              |
| --------------------- | ------------------------- | ------------------------ |
| `NEXT_PUBLIC_API_URL` | `http://localhost:8000`    | Laravel API base URL     |
