# QuinielaLab

Aplicación privada para estudiar y auditar estrategias de lotería de forma
reproducible. Fase 1B añade una ingesta manual, auditable e idempotente de sorteos;
no calcula métodos, señales, pagos, capital, backtesting ni palés.

## Arquitectura

```text
apps/api   Laravel 13 · PHP 8.4 · Sanctum · Horizon
apps/web   React 19.2 · Vite 8.2 · TypeScript · shadcn/ui · Tailwind 4.3.3
mysql      MySQL 8.4
redis      Redis · caché, sesión, cola y heartbeat
```

La API REST versionada vive bajo `/api/v1`. React consume un cliente `fetch`
encapsulado, con cookies stateful, CSRF y TanStack Query. En producción, Nginx sirve
la SPA y reenvía `/api` y `/sanctum` al Nginx interno de Laravel.

## Requisitos

- Docker con Compose.
- PHP 8.4 y Composer 2 para pruebas y herramientas locales.
- Node.js 22 y npm.
- Chromium de Playwright para E2E (`npx playwright install chromium`).

## Instalación local

Desde un clon limpio:

```bash
make setup
```

El comando instala dependencias bloqueadas, construye las imágenes, inicia MySQL y
Redis, ejecuta una única migración con lock y levanta los siete servicios. Abra
`http://localhost:5173`. Si ese puerto está ocupado:

```bash
WEB_PORT=5174 make up
```

Cree el único propietario mediante el prompt seguro:

```bash
docker compose exec api php artisan app:create-owner
```

Para automatización, use `--name`, `--email` y `--password-stdin`; no coloque la
contraseña en argumentos ni archivos versionados.

## Comandos habituales

```text
make up          inicia y espera salud
make down        detiene sin borrar volúmenes
make logs        sigue logs de todos los servicios
make migrate     aplica migraciones con lock
make test        ejecuta Pest y Vitest con MySQL/Redis reales
make lint        ejecuta Pint, PHPStan, ESLint y TypeScript
make build       compila frontend e imágenes Docker
make e2e         ejecuta Playwright contra un stack preparado
make shell-api   abre shell del contenedor PHP
make shell-web   abre shell del contenedor web
```

La verificación completa no destructiva está en:

```bash
./scripts/verify-phase0.sh
```

## Sincronización manual de sorteos (Fase 1B)

El provider `fake` es seguro por defecto y no usa Internet:

```bash
docker compose exec api php artisan draws:sync --provider=fake --lottery=5 --dry-run
```

El real se habilita solo con variables configuradas fuera de Git. Consulta el
sorteo actual y exige `--lottery` como ID externo; no admite fecha, rango ni
reconciliación en esta fase:

```bash
docker compose exec api php artisan draws:sync --provider=elboletoganador --lottery=5
```

La clave nunca se coloca en comandos, documentación, URL compartida ni Git. Los
secretos se muestran como `[REDACTED]`; repetir un resultado no crea duplicados,
un cambio deja una corrección append-only y un payload inválido entra a
cuarentena. Fase 1B no agenda sincronizaciones, no activa polling y no incorpora
pantalla de sorteos. Consulte [la guía operativa de Fase 1B](docs/phases/PHASE_01B_DRAW_PROVIDER.md).

## Endpoints de Fase 0

| Método | Ruta | Acceso | Propósito |
| --- | --- | --- | --- |
| GET | `/api/health` | Público | Estado de aplicación, MySQL, Redis y scheduler |
| GET | `/sanctum/csrf-cookie` | Público | Cookie CSRF para la SPA |
| POST | `/api/v1/auth/login` | Público limitado | Iniciar sesión, sin registro público |
| GET | `/api/v1/auth/me` | Autenticado | Usuario actual |
| POST | `/api/v1/auth/logout` | Autenticado | Invalidar sesión y token CSRF |

## Documentación

- [Variables de entorno](docs/ENVIRONMENT.md)
- [Despliegue en Dokploy](docs/DOKPLOY_DEPLOYMENT.md)
- [Entrega verificable de Fase 0](docs/PHASE_00_DELIVERY.md)
- [Arquitectura](docs/ARCHITECTURE.md)
- [Reglas de dominio](docs/DOMAIN_RULES.md)
- [Plan de Fase 0](docs/phases/PHASE_00_FOUNDATION.md)
- [Proveedor de sorteos de Fase 1B](docs/phases/PHASE_01B_DRAW_PROVIDER.md)

## Datos aportados

Los archivos de análisis (`.xlsx`, `.zip` y dumps SQL) quedan fuera de las imágenes
y no se importan automáticamente. La base histórica aportada contiene datos de
sorteos de fases futuras y se conserva intacta para un proceso posterior de perfilado,
normalización e importación idempotente.
# QuinielaLab

La operación de sorteos actuales está disponible en `/sorteos`. Consulte `docs/phases/PHASE_01C_DRAW_OPERATIONS.md` para configurar el provider fake o real sin importar historial.
