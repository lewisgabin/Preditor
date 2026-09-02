# Phase 0 Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the complete executable QuinielaLab Phase 0 foundation with private SPA authentication, observable infrastructure, CI, Docker, and no lottery-domain implementation.

**Architecture:** Laravel is the sole REST source of truth; a React/Vite SPA consumes it through Sanctum stateful cookies. MySQL and Redis back persistence, sessions, cache, queues, Horizon, rate limits, and scheduler heartbeat. Local Compose includes both datastores; production Compose expects Dokploy-managed MySQL and Redis.

**Tech Stack:** PHP 8.4, Laravel 13, Sanctum, Horizon, MySQL 8.4, Redis, Pest, Larastan, React 19.2.8, TypeScript, Vite 8.2.2, shadcn/ui CLI 4.19.1, Tailwind CSS 4.3.3, TanStack Query 5.102.8, React Router 7.18.3, Vitest 4.1.11, Playwright 1.62.1, Nginx, Docker Compose, GitHub Actions.

**Approved prerequisite:** The user approved replacing Ant Design with shadcn/ui and Tailwind. That decision is already recorded in `AGENTS.md`, `docs/TECH_STACK.md`, `docs/VERIFIED_VERSIONS.md`, and both Phase 0 specification copies before this implementation plan.

---

### Task 1: Establish a clean branch and repository baseline

**Files:**
- Create: `.gitignore`
- Modify: `README.md`
- Verify: `AGENTS.md`
- Verify: `docs/phases/PHASE_00_FOUNDATION.md`

- [ ] **Step 1: Verify branch and user-owned files**

Run: `git status --short --branch && git branch --show-current`
Expected: inspect the actual branch and preserve all pre-existing user files.

Run: `git switch codex/phase-00-foundation || git switch -c codex/phase-00-foundation`
Expected: `git branch --show-current` prints `codex/phase-00-foundation` before any implementation edit.

- [ ] **Step 2: Add repository exclusions**

Create `.gitignore` for `.DS_Store`, `.env`, `vendor/`, `node_modules/`, Laravel runtime/cache, coverage, Vite builds, Playwright output, IDE files, and temporary artifacts. Keep `.env.example`, the Excel, and `docs/elboletoganador.sql.zip` trackable.

- [ ] **Step 3: Verify the approved UI decision**

Run: `rg -n 'Ant Design|AntDesign' AGENTS.md README.md CODEX_KICKOFF.md CODEX_PHASE_0_PROMPT.md Especificacion_Fase0_QuinielaLab.md docs`
Expected: no Ant Design stack requirement remains.

- [ ] **Step 4: Commit the canonical specification baseline**

Run: `git add .gitignore AGENTS.md README.md CODEX_KICKOFF.md CODEX_PHASE_0_PROMPT.md Especificacion_Fase0_QuinielaLab.md ISSUE_001_PHASE_0.md REPOSITORY_SETUP.md docs/API_CONTRACT_TEMPLATE.md docs/ARCHITECTURE.md docs/CODEX_WORKFLOW.md docs/DATABASE_SCHEMA.md docs/DOKPLOY_DEPLOYMENT.md docs/DOMAIN_RULES.md docs/PRODUCT_SPEC.md docs/ROADMAP.md docs/TECH_STACK.md docs/VERIFIED_VERSIONS.md docs/phases/PHASE_00_FOUNDATION.md docs/superpowers/plans/2026-09-02-phase-00-foundation.md scripts/verify-phase0.sh`

Run: `git commit -m "docs: define QuinielaLab phase 0 foundation"`
Expected: first commit succeeds without `.DS_Store`, credentials, generated dependencies, or unrelated deletions.

### Task 2: Scaffold backend and frontend with pinned dependencies

**Files:**
- Create: `apps/api/**`
- Create: `apps/web/**`
- Create: `apps/web/components.json`

- [ ] **Step 1: Create Laravel using PHP 8.4**

Run from repository root:

```bash
php -r 'exit(PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 4 ? 0 : 1);'
composer create-project laravel/laravel:^13.0 apps/api --no-interaction
```

Expected: the version precheck passes, `apps/api/artisan` exists, and `php apps/api/artisan --version` reports Laravel 13. The caller may select any PHP 8.4 executable through its environment; no host-specific path is committed.

- [ ] **Step 2: Install backend packages**

Run in `apps/api`:

```bash
composer require laravel/sanctum laravel/horizon
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan
php artisan install:api --no-interaction
php artisan horizon:install
```

Expected: Sanctum and Horizon configuration/migrations exist; no domain tables are created.

- [ ] **Step 3: Create Vite application and pin runtime versions**

Run from repository root:

```bash
npm create vite@9.2.0 apps/web -- --template react-ts
cd apps/web
npm install react@19.2.8 react-dom@19.2.8 vite@8.2.2 @vitejs/plugin-react@6.1.1
npm install @tanstack/react-query@5.102.8 react-router-dom@7.18.3
npm install tailwindcss@4.3.3 @tailwindcss/vite@4.3.3
npm install -D vitest@4.1.11 @playwright/test@1.62.1 @testing-library/react @testing-library/jest-dom @testing-library/user-event jsdom eslint
npx shadcn@4.19.1 init --template vite --base radix --yes
```

Expected: `package-lock.json` pins effective versions, `components.json` exists, and Tailwind imports through `src/index.css`.

- [ ] **Step 4: Add only used shadcn components**

Run in `apps/web`:

```bash
npx shadcn@4.19.1 add button card input field alert badge skeleton separator sheet sidebar sonner
```

Expected: components are added as owned source under the alias reported by `npx shadcn@4.19.1 info`.

- [ ] **Step 5: Baseline validation**

Run: `composer --working-dir=apps/api validate --strict`

Run: `npm --prefix apps/web run build`

Expected: both commands pass before custom behavior.

- [ ] **Step 6: Commit scaffolds**

Run: `git add apps/api apps/web && git commit -m "build: scaffold Laravel and React applications"`

### Task 3: Configure MySQL, Redis, testing, and modular boundaries first

**Files:**
- Modify: `apps/api/.env.example`
- Modify: `apps/api/phpunit.xml`
- Modify: `apps/api/config/database.php`
- Modify: `apps/api/config/cache.php`
- Modify: `apps/api/config/queue.php`
- Modify: `apps/api/config/session.php`
- Modify: `apps/api/config/cors.php`
- Create: `apps/api/phpstan.neon`
- Create: `apps/api/app/Domain/Identity/README.md`
- Create: `apps/api/app/Domain/Shared/README.md`
- Create: `apps/api/app/Application/README.md`
- Create: `apps/api/app/Infrastructure/README.md`
- Create: `docker-compose.dependencies.yml`
- Test: `apps/api/tests/Feature/EnvironmentConfigurationTest.php`

- [ ] **Step 1: Define safe environment contracts**

Use MySQL, Redis queue/cache/session, `America/Santo_Domingo`, `APP_VERSION`, `GIT_SHA`, `FRONTEND_URL`, `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, and `CORS_ALLOWED_ORIGINS`. Use placeholders only; never real hosts, keys, tokens, emails, or passwords.

- [ ] **Step 2: Create bounded test dependencies**

Add `docker-compose.dependencies.yml` with MySQL 8.4 and Redis, health checks, persistent named volumes, `America/Santo_Domingo`, and finite retry/health intervals.

Run: `docker compose -f docker-compose.dependencies.yml up -d --wait`
Expected: MySQL and Redis become healthy or execution stops with the missing-Docker blocker; never replace MySQL with SQLite.

- [ ] **Step 3: Prove tests target MySQL and Redis**

Add a Pest configuration assertion that `DB_CONNECTION=mysql`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, and `SESSION_DRIVER=redis` under integration tests.

Run: `(cd apps/api && php artisan test tests/Feature/EnvironmentConfigurationTest.php)`
Expected RED: assertions fail because the test environment is not yet configured for the required MySQL/Redis stores.

Implement the test environment.

Run: `(cd apps/api && php artisan test tests/Feature/EnvironmentConfigurationTest.php)`
Expected GREEN: test passes against MySQL/Redis configuration.

- [ ] **Step 4: Document modular boundaries and static analysis**

Each required directory README states its responsibility and explicitly forbids lottery modules in Phase 0. Configure Larastan without suppressing new errors.

- [ ] **Step 5: Commit environment foundation**

Run: `git add apps/api docker-compose.dependencies.yml && git commit -m "build: configure MySQL Redis and backend quality"`

### Task 4: Implement the health contract with TDD

**Files:**
- Create: `apps/api/app/Application/Health/GetHealthStatus.php`
- Create: `apps/api/app/Http/Controllers/HealthController.php`
- Modify: `apps/api/routes/web.php`
- Modify: `apps/api/routes/console.php`
- Test: `apps/api/tests/Feature/HealthTest.php`

- [ ] **Step 1: Write failing endpoint tests**

Test JSON shape for `application`, `mysql`, `redis`, `scheduler`, optional `version`/`git_sha`; test HTTP 200 when healthy and 503 when a dependency is degraded; assert response never contains `DB_HOST`, `REDIS_HOST`, passwords, exception messages, or stack traces.

Run: `(cd apps/api && php artisan test tests/Feature/HealthTest.php)`
Expected RED: `/api/health` is missing.

- [ ] **Step 2: Implement the minimum health use case**

The use case performs bounded `DB::select('select 1')`, Redis ping/cache checks, and reads a heartbeat cache key. The controller only maps the result to JSON/status.

- [ ] **Step 3: Implement scheduler heartbeat**

In `routes/console.php`, schedule a closure every minute using `withoutOverlapping()` to store the current UTC instant; present it using explicit `America/Santo_Domingo` domain context only where needed.

- [ ] **Step 4: Run tests**

Run: `(cd apps/api && php artisan test tests/Feature/HealthTest.php)`
Expected GREEN: all health cases pass.

- [ ] **Step 5: Commit**

Run: `git add apps/api && git commit -m "feat(api): add dependency health endpoint"`

### Task 5: Implement private Sanctum authentication and owner command with TDD

**Files:**
- Create: `apps/api/app/Http/Controllers/Api/V1/AuthController.php`
- Create: `apps/api/app/Http/Requests/Auth/LoginRequest.php`
- Create: `apps/api/app/Http/Resources/UserResource.php`
- Create: `apps/api/app/Console/Commands/CreateOwner.php`
- Modify: `apps/api/routes/api.php`
- Modify: `apps/api/bootstrap/app.php`
- Modify: `apps/api/app/Providers/AppServiceProvider.php`
- Verify/Modify: `apps/api/database/factories/UserFactory.php`
- Test: `apps/api/tests/Feature/AuthTest.php`
- Test: `apps/api/tests/Feature/CreateOwnerCommandTest.php`

- [ ] **Step 1: Write authentication tests**

Cover valid login, generic invalid credentials, login rate limit, authenticated/unauthenticated `me`, logout/session invalidation, stateful cookie flow, and absence of registration routes.

Run: `(cd apps/api && php artisan test tests/Feature/AuthTest.php)`
Expected RED: routes/controllers are absent.

- [ ] **Step 2: Implement minimum auth contract**

Expose only:

```text
GET  /api/v1/auth/me
POST /api/v1/auth/login
POST /api/v1/auth/logout
```

Use session regeneration on login, session invalidation and CSRF token regeneration on logout, `auth:sanctum` for `me`/logout, a named limiter by normalized email+IP, and a resource that returns only id/name/email.

- [ ] **Step 3: Run authentication tests**

Run: `(cd apps/api && php artisan test tests/Feature/AuthTest.php)`
Expected GREEN: all auth cases pass with MySQL and Redis.

- [ ] **Step 4: Write owner-command tests**

Cover interactive/options creation, hashing, duplicate rejection, validation, and non-disclosure of password.

Run: `(cd apps/api && php artisan test tests/Feature/CreateOwnerCommandTest.php)`
Expected RED: command is absent.

- [ ] **Step 5: Implement and retest owner command**

Command: `app:create-owner {--name=} {--email=} {--password=} {--password-stdin}`. Missing values prompt interactively; `--password-stdin` supports E2E automation without exposing the password in process arguments or logs. No credentials are persisted outside the users table.

Run: `(cd apps/api && php artisan test tests/Feature/CreateOwnerCommandTest.php)`
Expected GREEN: command tests pass.

- [ ] **Step 6: Backend quality gate and commit**

Run:

```bash
composer --working-dir=apps/api validate --strict
(cd apps/api && vendor/bin/pint --test)
(cd apps/api && vendor/bin/phpstan analyse -c phpstan.neon)
(cd apps/api && php artisan test)
```

Expected: all pass.

Run: `git add apps/api && git commit -m "feat(api): add private SPA authentication"`

### Task 6: Build the typed SPA authentication flow with TDD

**Files:**
- Modify: `apps/web/vite.config.ts`
- Modify: `apps/web/tsconfig.json`
- Modify: `apps/web/tsconfig.app.json`
- Modify: `apps/web/src/index.css`
- Create: `apps/web/src/shared/api/contracts.ts`
- Create: `apps/web/src/shared/api/http-client.ts`
- Create: `apps/web/src/features/auth/api.ts`
- Create: `apps/web/src/features/auth/ProtectedRoute.tsx`
- Create: `apps/web/src/pages/LoginPage.tsx`
- Create: `apps/web/src/pages/NotFoundPage.tsx`
- Create: `apps/web/src/app/router.tsx`
- Modify: `apps/web/src/main.tsx`
- Test: `apps/web/src/features/auth/auth.test.tsx`

- [ ] **Step 1: Configure strict aliases and test environment**

Add `@/* -> src/*`, `@tailwindcss/vite`, jsdom, Testing Library setup, and scripts `lint`, `typecheck`, `test`, `build`, `test:e2e`.

- [ ] **Step 2: Write failing auth-flow tests**

Test CSRF acquisition, every request using `credentials: 'include'`, Spanish invalid-login feedback, loading/disabled submit, redirect to `/` after login, redirect to `/login` for unauthenticated access, logout, and Spanish 404.

Run: `npm --prefix apps/web run test -- --run src/features/auth/auth.test.tsx`
Expected RED: client/pages/routes are absent.

- [ ] **Step 3: Implement the minimal typed client and pages**

Use one encapsulated `fetch`, explicit response/error types, TanStack Query mutations/queries, shadcn `FieldGroup`/`Field`, semantic tokens, Sonner, and no Axios or domain calculations.

- [ ] **Step 4: Retest and commit**

Run: `npm --prefix apps/web run test -- --run src/features/auth/auth.test.tsx`
Expected GREEN: focused tests pass.

Run: `git add apps/web && git commit -m "feat(web): add private login flow"`

### Task 7: Build the provisional dashboard with observable states

**Files:**
- Create: `apps/web/src/features/health/api.ts`
- Create: `apps/web/src/features/health/HealthStatus.tsx`
- Create: `apps/web/src/shared/layout/AppShell.tsx`
- Create: `apps/web/src/pages/DashboardPage.tsx`
- Test: `apps/web/src/pages/DashboardPage.test.tsx`

- [ ] **Step 1: Write failing dashboard tests**

Cover loading skeleton, healthy services, degraded MySQL/Redis/heartbeat, API unavailable, authenticated owner, Spanish empty/error copy, and disabled modules named exactly «Señales, Métodos, Capital, Backtesting y Palés».

Run: `npm --prefix apps/web run test -- --run src/pages/DashboardPage.test.tsx`
Expected RED: dashboard is absent.

- [ ] **Step 2: Implement responsive dashboard**

Use shadcn Card/Badge/Alert/Skeleton/Sidebar or Sheet. Base styles are mobile; enhance at `md`/`lg`. Require 44px targets, semantic color tokens, visible focus, no horizontal overflow, and accessible headings/status text.

- [ ] **Step 3: Frontend quality gate**

Run:

```bash
npm --prefix apps/web run lint
npm --prefix apps/web run typecheck
npm --prefix apps/web run test -- --run
npm --prefix apps/web run build
```

Expected: all pass.

- [ ] **Step 4: Commit**

Run: `git add apps/web && git commit -m "feat(web): add phase 0 health dashboard"`

### Task 8: Implement local and production containers

**Files:**
- Create: `infra/docker/api/Dockerfile`
- Create: `infra/docker/api/entrypoint.sh`
- Create: `infra/docker/web/Dockerfile`
- Create: `infra/nginx/api.conf`
- Create: `infra/nginx/web.conf`
- Create: `docker-compose.yml`
- Create: `docker-compose.prod.yml`
- Create: `scripts/release.sh`
- Modify: `docs/DOKPLOY_DEPLOYMENT.md`

- [ ] **Step 1: Create production images**

Use pinned PHP 8.4 and Node 22 bases, multi-stage dependency/build layers, explicit non-root runtime users, stdout/stderr logs, health checks, and `America/Santo_Domingo`. PHP-FPM must not publish a host port.

- [ ] **Step 2: Create local Compose**

Include web, api-nginx, api, horizon, scheduler, MySQL 8.4, and Redis. Add bounded health checks and `depends_on: condition: service_healthy`; no infinite wait loop and no automatic migrations in web/worker/scheduler.

- [ ] **Step 3: Create production Compose**

Include only web, api-nginx, api, horizon, scheduler. Expose only Nginx services per subdomain; document resource guidance rather than irreversible hard limits. Images accept SHA tags for rollback.

- [ ] **Step 4: Create distributed release command**

`scripts/release.sh` executes exactly `php artisan migrate --isolated --force` with `CACHE_STORE=redis`, propagates its exit code, and never runs migrations from service entrypoints. Laravel's `--isolated` mechanism supplies the distributed cache lock; no shell-evaluated code or container-local file lock is allowed.

- [ ] **Step 5: Validate and build**

Run:

```bash
docker compose config --quiet
docker compose -f docker-compose.prod.yml config --quiet
docker compose build
docker compose -f docker-compose.prod.yml build
docker compose up -d --wait
docker compose exec -T api id -u
docker compose exec -T horizon id -u
docker compose exec -T scheduler id -u
docker compose exec -T web id -u
docker compose exec -T api-nginx id -u
docker compose exec -T api php artisan horizon:status
docker compose ps --format json
IMAGE_TAG=$(git rev-parse --short HEAD) docker compose -f docker-compose.prod.yml config
```

Expected: all pass; runtime UIDs are not `0`; Horizon reports running; scheduler heartbeat becomes healthy within a documented finite timeout; PHP-FPM has no published host port; production config resolves the requested SHA image tag. Any unavoidable root exception must identify the exact process and justification in `docs/DOKPLOY_DEPLOYMENT.md`.

- [ ] **Step 6: Commit**

Run: `git add infra docker-compose.yml docker-compose.prod.yml scripts/release.sh docs/DOKPLOY_DEPLOYMENT.md && git commit -m "build: add local and Dokploy containers"`

### Task 9: Add Makefile, CI, dependency updates, and E2E

**Files:**
- Create: `Makefile`
- Create: `.github/workflows/ci.yml`
- Create: `.github/dependabot.yml`
- Create: `.github/pull_request_template.md`
- Create: `apps/web/playwright.config.ts`
- Create: `apps/web/e2e/auth-dashboard.spec.ts`
- Create: `scripts/wait-for-health.sh`
- Modify: `scripts/verify-phase0.sh`

- [ ] **Step 1: Add developer commands**

Implement `setup`, `up`, `down`, `logs`, `migrate`, `test`, `lint`, `build`, `shell-api`, and `shell-web`. `setup` copies examples only when missing, installs locked dependencies, starts dependencies, migrates once, and reports next steps without embedding credentials.

- [ ] **Step 2: Write E2E acceptance test**

Set Playwright `baseURL` from `E2E_BASE_URL`, define mobile 390x844 and desktop 1440x900 projects, retain trace/screenshot/video on failure, and assert a clean browser console. The flow visits `/login`, authenticates, sees the protected dashboard and healthy status, then logs out.

Create `scripts/wait-for-health.sh URL TIMEOUT_SECONDS` with a one-second bounded poll and nonzero timeout exit. Execute E2E with cleanup guaranteed by a shell trap:

```bash
set +x
export E2E_OWNER_EMAIL="owner-e2e@example.test"
export E2E_OWNER_PASSWORD="$(openssl rand -base64 24)"
export COMPOSE_PROJECT_NAME="quinielalab-e2e-${GITHUB_RUN_ID:-$$}"
trap 'docker compose -p "$COMPOSE_PROJECT_NAME" down -v' EXIT
docker compose -p "$COMPOSE_PROJECT_NAME" up -d --build --wait
docker compose -p "$COMPOSE_PROJECT_NAME" exec -T api php artisan migrate --isolated --force
printf '%s\n' "$E2E_OWNER_PASSWORD" | docker compose -p "$COMPOSE_PROJECT_NAME" exec -T api php artisan app:create-owner --name="E2E Owner" --email="$E2E_OWNER_EMAIL" --password-stdin
scripts/wait-for-health.sh http://127.0.0.1/api/health 90
E2E_BASE_URL=http://127.0.0.1 npm --prefix apps/web run test:e2e
```

Expected: both viewports pass; credentials are masked/not echoed; cleanup runs on success or failure.

- [ ] **Step 3: Create CI for PR and main**

Jobs:

- Backend with MySQL 8.4 and Redis health checks: `composer validate`, locked install, Pint, PHPStan, Pest.
- Frontend: `npm ci`, lint, typecheck, Vitest, build.
- E2E: integrated services and Playwright.
- Containers: build API/web images on PR without publishing; on `main`, prepare SHA-tagged image metadata/artifacts without pushing credentials from PR contexts.

- [ ] **Step 4: Dependabot and PR template**

Dependabot is included because the approved Phase 0 kickoff explicitly requests Composer/npm updates. The PR template records summary, decisions, migrations, tests, risks, and pending work.

- [ ] **Step 5: Expand verification script**

Require structure, backend/frontend gates, Playwright, Compose validation, and both image builds. Do not silently skip unavailable commands.

- [ ] **Step 6: Commit**

Run: `git add Makefile .github apps/web/playwright.config.ts apps/web/e2e scripts/wait-for-health.sh scripts/verify-phase0.sh && git commit -m "ci: verify phase 0 across the stack"`

### Task 10: Final documentation, runtime acceptance, and PR

**Files:**
- Modify: `README.md`
- Modify: `docs/phases/PHASE_00_FOUNDATION.md`
- Modify: `Especificacion_Fase0_QuinielaLab.md`

- [ ] **Step 1: Document clean installation and operations**

Cover prerequisites, environments, `make setup`, owner creation, local URLs, quality commands, Dokploy variables, release/rollback by SHA, backup responsibility, and troubleshooting. Keep both Phase 0 specification copies byte-identical or replace the root copy with a pointer.

- [ ] **Step 2: Run complete verification**

Run: `bash scripts/verify-phase0.sh`
Expected: exit 0.

- [ ] **Step 3: Verify observable runtime acceptance**

Run the integrated stack and verify:

- `/api/health` reports application/MySQL/Redis/heartbeat without secrets.
- Horizon process is running.
- Scheduler updates heartbeat after at least one minute.
- Login cookies are HttpOnly; production configuration sets Secure and appropriate SameSite.
- CORS is an explicit allowlist and never `*` with credentials.
- Login/dashboard work on mobile and desktop with clean browser console.

- [ ] **Step 4: Security and scope scans**

Run:

```bash
rg -n -i --hidden -g '!.git/**' -g '!vendor/**' -g '!node_modules/**' '(password\s*=|token\s*=|api[_-]?key\s*=|BEGIN.*PRIVATE KEY)' .
git ls-files | rg '(^|/)\.env($|\.)' | rg -v '\.env\.example$'
rg -n --hidden -g '!.git/**' -g '!vendor/**' -g '!node_modules/**' '(https?://(localhost|127\.0\.0\.1|[a-z0-9.-]+\.internal)|[a-z0-9.-]+\.local)' .
rg -n -i 'Lottery|Draw|Signal|Bankroll|Backtest|Pale|Quiniela|Sorteo|Señal|Capital' apps/api/app apps/web/src
git diff --check
```

Expected: no tracked `.env`, real secrets, or private production URLs; out-of-scope search results are limited to explicitly disabled Spanish dashboard labels or documentation and are reviewed individually; no Phase 1+ domain modules or whitespace errors.

- [ ] **Step 5: Final commit**

Run: `git add README.md docs/DOKPLOY_DEPLOYMENT.md docs/phases/PHASE_00_FOUNDATION.md Especificacion_Fase0_QuinielaLab.md && git commit -m "docs: complete phase 0 operations guide"`

- [ ] **Step 6: Push and PR**

Run: `git remote -v`.

If a remote exists:

```bash
git push -u origin codex/phase-00-foundation
gh pr create --base main --head codex/phase-00-foundation --title "feat: complete phase 0 foundation" --body-file /tmp/phase0-pr.md
```

If no remote exists, stop only this publication step and report the exact blocker; do not invent a URL or remote.
