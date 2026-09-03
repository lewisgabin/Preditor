# Despliegue de QuinielaLab en Dokploy

## Topología

Dokploy construye `docker-compose.prod.yml`. La aplicación contiene únicamente
`web`, `api-nginx`, `api`, `horizon` y `scheduler`; MySQL 8.4 y Redis son servicios
administrados externos. PHP-FPM permanece en la red interna y los únicos servicios
HTTP son `web` y `api-nginx`, cada uno asociado a su propio subdominio.

| Ambiente | Rama | Web | API |
| --- | --- | --- | --- |
| Staging | `staging` | `staging-app.dominio.com` | `staging-api.dominio.com` |
| Producción | `main` | `app.dominio.com` | `api.dominio.com` |

## Variables obligatorias

Configure secretos en Dokploy, nunca en Git:

```text
APP_KEY
APP_URL
DB_HOST
DB_DATABASE
DB_USERNAME
DB_PASSWORD
REDIS_HOST
SESSION_DOMAIN
SANCTUM_STATEFUL_DOMAINS
CORS_ALLOWED_ORIGINS
LOTTERY_API_ENABLED
LOTTERY_API_PROVIDER
LOTTERY_API_BASE_URL
LOTTERY_API_KEY
```

Variables opcionales con valores por defecto seguros:

```text
APP_VERSION=production
GIT_SHA=latest
DB_PORT=3306
REDIS_PORT=6379
REDIS_PASSWORD=null
API_IMAGE=quinielalab-api
API_NGINX_IMAGE=quinielalab-api-nginx
WEB_IMAGE=quinielalab-web
IMAGE_TAG=latest
```

`APP_URL` debe apuntar al subdominio de API. `SESSION_DOMAIN` debe ser el dominio
compartido (por ejemplo, `.dominio.com`) y las listas de Sanctum/CORS deben contener
el origen web exacto, sin comodines. Genere `APP_KEY` con `php artisan key:generate
--show` en un entorno seguro.

## Proveedor de sorteos de Fase 1B

Conserve `LOTTERY_API_KEY` exclusivamente como secreto de Dokploy. El proveedor
real usa una clave en la ruta, por lo que no debe aparecer en URLs, comandos,
logs, metadata ni capturas; el adaptador la representa como `[REDACTED]`.

Para una instalación sin red use `LOTTERY_API_ENABLED=false` y
`LOTTERY_API_PROVIDER=fake`. Para el real use `LOTTERY_API_PROVIDER=elboletoganador`
y configure base URL y clave en secretos; no se documentan sus valores. El real
solo consulta el sorteo actual de una lotería por ejecución manual. No admite
fecha, rango ni reconciliación. Reintenta de forma acotada red, timeout y HTTP
408, 429 y 5xx; 429 respeta `Retry-After`. Autenticación y 4xx permanentes no se
reintentan indefinidamente.

## Release y migraciones

Ejecute una sola vez por despliegue, dentro de un contenedor de API:

```bash
./scripts/release.sh
```

El script usa `php artisan migrate --isolated --force`; el lock impide migraciones
concurrentes. No configure migraciones automáticas en `api`, `horizon` ni
`scheduler`, y nunca ejecute `migrate:fresh` en producción.

Orden recomendado:

1. Construir imágenes etiquetadas con el SHA del commit.
2. Ejecutar el comando de release en una sola instancia de API.
3. Iniciar o actualizar `api`, `horizon`, `scheduler`, `api-nginx` y `web`.
4. Comprobar `/api/health`, el estado de Horizon y el dashboard privado.

## Salud, recursos y seguridad

- Web: `/`.
- API: `/api/health`; valida aplicación, MySQL, Redis y heartbeat del scheduler.
- Horizon: `php artisan horizon:status`.
- Scheduler: heartbeat renovado cada minuto.
- Logs: stdout/stderr.
- Cookies: `Secure`, `HttpOnly` y sesión cifrada en producción.

Punto de partida, ajustable con métricas del VPS: API 0.5–1 CPU y 512 MB–1 GB;
Horizon 0.5 CPU y 256–512 MB; web 128 MB; scheduler bajo consumo. Estos límites
se documentan y no se fijan en el Compose para evitar restricciones irreversibles.

El servicio `scheduler` existente mantiene el heartbeat de Fase 0. Fase 1B no
registra polling ni comandos automáticos de sorteos; no los agregue en Dokploy
hasta una fase posterior aprobada.

## Rollback

Conserve las imágenes por SHA. Para volver atrás, cambie `IMAGE_TAG` al SHA estable
anterior y redespliegue los cinco servicios. Las migraciones deben ser compatibles
hacia atrás; si alguna requiere reversión, use un procedimiento revisado y una copia
de seguridad, nunca `migrate:fresh`.

## Backups

- MySQL diario con al menos 14 copias.
- Copia externa semanal.
- Redis persistente según el nivel de recuperación requerido.
- Prueba de restauración antes de validar operaciones con dinero.
- Secretos y procedimiento de recuperación guardados fuera de Git.
# Sorteos actuales

Configure las variables `LOTTERY_SYNC_*` en Dokploy como secretos/variables de entorno. No las agregue al Compose. Active la automatización únicamente después de comprobar el provider; el scheduler ejecuta el dispatcher y Redis evita duplicados.
