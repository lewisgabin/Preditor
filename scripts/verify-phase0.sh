#!/usr/bin/env bash
set -euo pipefail

required=(
  "apps/api/composer.json"
  "apps/api/artisan"
  "apps/web/package.json"
  "docker-compose.yml"
  "docker-compose.prod.yml"
  ".github/workflows/ci.yml"
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
  vendor/bin/phpstan analyse
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
docker compose -f docker-compose.prod.yml config --quiet

echo "Fase 0 verificada correctamente."
