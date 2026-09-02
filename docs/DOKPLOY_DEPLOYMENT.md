# Plan de despliegue en Dokploy

## Decisión

Usar Docker Compose desde el repositorio GitHub.

Dokploy administrará por separado:

- MySQL.
- Redis.
- Backups y volúmenes.

El compose de la aplicación contendrá:

- Frontend.
- Nginx de la API.
- PHP-FPM.
- Horizon.
- Scheduler.

## Ambientes

### Staging

```text
staging-app.dominio.com
staging-api.dominio.com
```

Rama: `staging`.

### Producción

```text
app.dominio.com
api.dominio.com
```

Rama: `main`.

Para un MVP personal se puede comenzar solamente con producción, pero la configuración debe permitir staging.

## Variables

Backend:

```text
APP_ENV
APP_KEY
APP_URL
FRONTEND_URL
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
REDIS_HOST
REDIS_PORT
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_DOMAIN
SANCTUM_STATEFUL_DOMAINS
CORS_ALLOWED_ORIGINS
LOTTERY_API_BASE_URL
LOTTERY_API_TOKEN
LOTTERY_API_TIMEOUT
```

Frontend:

```text
VITE_API_BASE_URL
VITE_APP_NAME
```

## Health checks

```text
web: /
api: /api/health
horizon: proceso activo
scheduler: heartbeat en cache/base
```

El dashboard debe alertar si:

- No se recibió un sorteo esperado.
- Horizon está detenido.
- El scheduler no registró heartbeat.
- La API externa falla repetidamente.

## Proceso de despliegue

1. Merge a `main`.
2. GitHub Actions ejecuta checks.
3. Dokploy detecta el push de la rama configurada.
4. Build de imágenes.
5. Migraciones con un comando de release controlado.
6. Reinicio de servicios.
7. Health check.
8. Verificación manual del dashboard.

No ejecutar `migrate:fresh` en producción.

## Backups

- MySQL diario.
- Retención mínima de 14 copias.
- Copia externa semanal.
- Prueba de restore antes de iniciar la validación con dinero.
- Variables y secretos documentados fuera de Git.

## Recursos iniciales sugeridos

Para una sola persona y el volumen actual:

```text
api:        0.5–1 CPU, 512 MB–1 GB
horizon:    0.5 CPU, 256–512 MB
scheduler:  bajo consumo
web:        128 MB
mysql:      1 GB o más
redis:      128–256 MB
```

Los límites finales dependen de la capacidad real del VPS y de otros proyectos alojados.
