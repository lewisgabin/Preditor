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

## Proveedor de sorteos (Fase 1B)

El provider `fake` es seguro para local/CI y no usa Internet. Para activar el
real, configure sus variables en el gestor de secretos del ambiente, nunca en
Git, logs o comandos compartidos.

| Variable | Requerida para real | Descripción |
| --- | --- | --- |
| `LOTTERY_API_ENABLED` | Sí | Habilita solicitudes; default `false` |
| `LOTTERY_API_PROVIDER` | Sí | `fake` o `elboletoganador` |
| `LOTTERY_API_BASE_URL` | Sí | Base pública sin incluir la clave |
| `LOTTERY_API_ENDPOINT_TEMPLATE` | No | Reservada para futuros contratos |
| `LOTTERY_API_KEY` | Sí, secreta | Clave usada solo en memoria como segmento de ruta |
| `LOTTERY_API_TIMEOUT_SECONDS` | No | Límite total; default `10` |
| `LOTTERY_API_CONNECT_TIMEOUT_SECONDS` | No | Límite de conexión; default `5` |
| `LOTTERY_API_RETRY_ATTEMPTS` | No | Máximo de intentos; default `3` |
| `LOTTERY_API_RETRY_BACKOFF_SECONDS` | No | Base de backoff; default `5` |
| `LOTTERY_API_LOOKBACK_DAYS` | No | Reservada; no amplía capacidades reales |
| `LOTTERY_API_RECONCILIATION_DAYS` | No | Reservada; no amplía capacidades reales |

`LOTTERY_API_TOKEN` y `DRAW_PROVIDER_TOKEN` son alias de compatibilidad para
`LOTTERY_API_KEY`; defina una sola variable secreta. La clave permanece vacía en
`.env.example`. El real solo tiene capacidad de sorteo actual por external ID;
fecha, rango y reconciliación se rechazan antes de HTTP. Los cuerpos y contextos
se sanitizan y muestran secretos solo como `[REDACTED]`.

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
# Sincronización de sorteos actuales

Mantenga `LOTTERY_SYNC_AUTOMATIC_ENABLED=false` hasta que el provider real esté configurado. En local use `LOTTERY_SYNC_PROVIDER=fake`; en producción, el provider real recibe su clave desde variables de entorno y nunca desde Git. La API real solo incorpora o corrige el sorteo actual; no importa historial.
