# Variables de entorno

## API

| Variable | Requerida en producción | Descripción |
| --- | --- | --- |
| `APP_ENV` | Sí | `production` en Dokploy |
| `APP_KEY` | Sí, secreta | Clave Laravel generada de forma segura |
| `APP_URL` | Sí | URL HTTPS pública de la API |
| `APP_TIMEZONE` | Sí | `America/Santo_Domingo` |
| `APP_VERSION` | No | Versión mostrada por health |
| `GIT_SHA` | No | SHA desplegado para trazabilidad/rollback |
| `APP_DEBUG` | Sí | Siempre `false` en producción |
| `LOG_CHANNEL` | Sí | `stderr` en contenedores |
| `DB_HOST` | Sí | Host MySQL administrado |
| `DB_PORT` | No | `3306` por defecto |
| `DB_DATABASE` | Sí | Base de la aplicación |
| `DB_USERNAME` | Sí | Usuario con privilegios mínimos |
| `DB_PASSWORD` | Sí, secreta | Contraseña MySQL |
| `REDIS_HOST` | Sí | Host Redis administrado |
| `REDIS_PORT` | No | `6379` por defecto |
| `REDIS_PASSWORD` | Según servicio | Contraseña Redis, si aplica |
| `REDIS_CLIENT` | Sí | `predis` |
| `CACHE_STORE` | Sí | `redis` |
| `QUEUE_CONNECTION` | Sí | `redis` |
| `SESSION_DRIVER` | Sí | `redis` |
| `SESSION_DOMAIN` | Sí | Dominio compartido de las cookies |
| `SESSION_SECURE_COOKIE` | Sí | `true` en producción |
| `SANCTUM_STATEFUL_DOMAINS` | Sí | Hosts SPA autorizados, separados por coma |
| `CORS_ALLOWED_ORIGINS` | Sí | Orígenes HTTPS exactos; nunca `*` |

`FRONTEND_URL`, `LOTTERY_API_BASE_URL`, `LOTTERY_API_TOKEN` y variables de lotería
no se consumen en Fase 0. Se añadirán únicamente con un contrato funcional aprobado.

## Frontend y Compose

La compilación usa rutas relativas porque Nginx proxyfica API y Sanctum en el mismo
origen. No necesita una URL privada incrustada en el bundle.

| Variable | Uso |
| --- | --- |
| `WEB_PORT` | Puerto web local; `5173` por defecto |
| `API_IMAGE` | Repositorio de imagen PHP en producción |
| `API_NGINX_IMAGE` | Repositorio de imagen Nginx API |
| `WEB_IMAGE` | Repositorio de imagen web |
| `IMAGE_TAG` | Etiqueta inmutable, preferiblemente el SHA |

## Archivos locales

`apps/api/.env.example` solo contiene nombres y valores de ejemplo. Copie a `.env`
si ejecuta Artisan fuera de Docker, genere una clave local y nunca versione `.env`.
Las credenciales `local_only` y `test_only` de Compose son públicas, deterministas y
exclusivas de entornos locales aislados; no sirven para producción.
