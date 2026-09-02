# Entrega de Fase 0

## Alcance entregado

- Monorepo `apps/api` y `apps/web`.
- Laravel 13 con Sanctum stateful SPA, Horizon, health y propietario por Artisan.
- React 19.2 con shadcn/ui, Tailwind CSS 4.3.3, TanStack Query y rutas protegidas.
- MySQL 8.4 y Redis para aplicación, pruebas, sesión, caché y colas.
- Compose local de siete servicios y Compose de producción de cinco servicios.
- CI para calidad backend/frontend, imágenes y E2E con credencial efímera.
- Procedimiento Dokploy con release aislado y rollback por SHA.

## Migraciones

```text
0001_01_01_000000_create_users_table
0001_01_01_000001_create_cache_table
0001_01_01_000002_create_jobs_table
2026_09_02_045325_create_personal_access_tokens_table
```

No existen tablas de loterías, sorteos, métodos, señales, capital, backtesting ni
palés. El dump histórico aportado no se importó.

## Verificación esperada

```bash
cd apps/api
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan test

cd ../web
npm run lint
npm run typecheck
npm run test -- --run
npm run build
npm run test:e2e

docker compose config --quiet
docker compose build
docker compose up -d --wait
curl --fail http://localhost:8080/api/health
```

La prueba E2E requiere un propietario local y `E2E_EMAIL`/`E2E_PASSWORD`. CI genera
la contraseña en memoria y la entrega al comando mediante stdin.

## Decisiones y límites

- shadcn/ui y Tailwind sustituyen Ant Design por decisión explícita del propietario.
- La API sigue siendo la fuente de verdad; React no incluye cálculos de dominio.
- MySQL no se sustituye por SQLite en integración.
- Ningún contenedor de aplicación se ejecuta como root.
- Las migraciones no se ejecutan al arrancar cada servicio.
- No hay registro público, SSR, Axios, Filament ni WebSockets.

## Riesgos pendientes

- Configurar dominios, TLS, secretos, backups y límites reales en Dokploy.
- Ejecutar el pipeline en GitHub cuando exista un remoto.
- Probar restauración MySQL antes de usar datos operativos.
- Diseñar y aprobar el contrato de sorteos antes de importar el dump histórico.
- Perfilar y corregir filas históricas anómalas mediante un importador idempotente y
  auditable en una fase posterior.
