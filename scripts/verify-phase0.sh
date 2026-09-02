#!/usr/bin/env bash
set -euo pipefail

required=(
  "apps/api/composer.json"
  "apps/api/artisan"
  "apps/web/package.json"
  "docker-compose.yml"
  "docker-compose.prod.yml"
  "docker-compose.dependencies.yml"
  ".github/workflows/ci.yml"
  "apps/web/playwright.config.ts"
  "apps/web/e2e/auth-dashboard.spec.ts"
  "Makefile"
)

missing=0
for path in "${required[@]}"; do
  if [[ ! -e "$path" ]]; then
    echo "FALTA: $path"
    missing=1
  fi
done

if [[ "$missing" -ne 0 ]]; then
  echo "La estructura de Fase 0 todavía está incompleta."
  exit 1
fi

(
  cd apps/api
  composer validate --strict
  vendor/bin/pint --test
  vendor/bin/phpstan analyse --debug
  php artisan test
)

(
  cd apps/web
  npm run lint
  npm run typecheck
  npm run test -- --run
  npm run build
)

docker compose config --quiet

: "${APP_KEY:=base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=}"
: "${APP_URL:=https://api.example.test}"
: "${DB_HOST:=mysql.example}"
: "${DB_DATABASE:=quinielalab}"
: "${DB_USERNAME:=quinielalab}"
: "${DB_PASSWORD:=placeholder}"
: "${REDIS_HOST:=redis.example}"
: "${SESSION_DOMAIN:=.example.test}"
: "${SANCTUM_STATEFUL_DOMAINS:=app.example.test}"
: "${CORS_ALLOWED_ORIGINS:=https://app.example.test}"
export APP_KEY APP_URL DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD REDIS_HOST
export SESSION_DOMAIN SANCTUM_STATEFUL_DOMAINS CORS_ALLOWED_ORIGINS
docker compose -f docker-compose.prod.yml config --quiet

echo "Fase 0 verificada correctamente."
