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
