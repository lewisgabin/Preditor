# Fase 0 — Fundación ejecutable

## Objetivo

Construir la base técnica completa de QuinielaLab sin implementar todavía sorteos, métodos, señales, capital, backtesting ni palés.

Al terminar, debe existir una aplicación privada con autenticación, una API Laravel, una SPA React, infraestructura Docker, cola Redis, CI y una ruta clara de despliegue a Dokploy.

## Stack fijado

- PHP 8.4.
- Laravel 13.x estable.
- Laravel Sanctum para autenticación SPA por cookie.
- Laravel Horizon sobre Redis.
- MySQL 8.4.
- React 19.2.
- TypeScript.
- Vite 8.2.x.
- shadcn/ui mediante el CLI oficial, con los componentes mantenidos como código del proyecto.
- Tailwind CSS 4.3.3 mediante `@tailwindcss/vite` 4.3.3.
- TanStack Query 5.x.
- Pest para backend.
- Vitest + Testing Library para frontend.
- Playwright para una prueba E2E mínima.
- Docker Compose.
- GitHub Actions.

## Estructura requerida

```text
apps/
  api/                         Laravel
  web/                         React + Vite
infra/
  docker/
  nginx/
docs/
.github/
AGENTS.md
Makefile
docker-compose.yml
docker-compose.prod.yml
```

## Backend

### API base

Prefijo obligatorio:

```text
/api/v1
```

Endpoints mínimos:

```text
GET  /api/health
GET  /api/v1/auth/me
POST /api/v1/auth/login
POST /api/v1/auth/logout
```

`GET /api/health` debe comprobar:

- aplicación;
- conexión MySQL;
- conexión Redis;
- versión/despliegue mediante variables opcionales `APP_VERSION` y `GIT_SHA`.

No debe revelar contraseñas, hosts privados ni stack traces.

### Autenticación

- Sanctum stateful SPA.
- Cookies `HttpOnly` y `Secure` en producción.
- CSRF.
- Rate limit para login.
- Sin registro público.
- Comando Artisan para crear el propietario:

```bash
php artisan app:create-owner
```

El comando debe solicitar nombre, correo y contraseña de forma interactiva; también debe aceptar opciones para automatización local sin almacenar credenciales en Git.

### Estructura modular inicial

Crear directorios y namespaces vacíos, documentados y cubiertos por autoload:

```text
app/Domain/Identity
app/Domain/Shared
app/Application
app/Infrastructure
```

No crear aún módulos de lotería.

### Calidad backend

- Pest configurado.
- Laravel Pint.
- Larastan/PHPStan.
- Tests de health.
- Tests de login válido, inválido, logout, `me` autenticado y no autenticado.
- Factories para usuario.
- Base de tests integrada con MySQL; no sustituir silenciosamente con SQLite.

## Frontend

### Pantallas

- `/login`.
- `/` dashboard protegido.
- página 404.

### Dashboard provisional

Debe mostrar:

- nombre QuinielaLab;
- usuario autenticado;
- estado de API;
- estado de MySQL;
- estado de Redis;
- bloque «Fase 0 completada»;
- módulos futuros deshabilitados visualmente: Señales, Métodos, Capital, Backtesting y Palés.

### UX

- Mobile-first.
- shadcn/ui con componentes accesibles y mantenidos dentro del repositorio.
- Tailwind CSS 4.3.3 con tokens semánticos y variables CSS.
- Tema claro inicial con tokens centralizados.
- Mensajes en español.
- Estados de carga, error y vacío.
- No colocar lógica de negocio futura en React.

### Cliente HTTP

- `fetch` encapsulado; no añadir Axios.
- `credentials: 'include'`.
- Manejo de CSRF de Sanctum.
- Tipos de respuesta explícitos.
- TanStack Query para estado remoto.

### Calidad frontend

- ESLint.
- TypeScript estricto.
- Vitest.
- Testing Library.
- Pruebas para login y dashboard/health.
- Una prueba Playwright: crear sesión de prueba, iniciar sesión y ver dashboard.

## Docker local

Servicios:

```text
web
api-nginx
api
horizon
scheduler
mysql
redis
```

Requisitos:

- un comando documentado inicia todo;
- volúmenes persistentes para MySQL y Redis;
- healthchecks;
- los servicios esperan dependencias sin bucles infinitos;
- `horizon` y `scheduler` usan la misma imagen de API;
- no ejecutar migraciones simultáneas desde varios contenedores;
- logs a stdout/stderr;
- zona horaria de negocio `America/Santo_Domingo`.

## Docker de producción / Dokploy

`docker-compose.prod.yml` debe incluir solamente:

```text
web
api-nginx
api
horizon
scheduler
```

MySQL y Redis se configurarán como servicios administrados de Dokploy.

Requisitos:

- imágenes multi-stage;
- frontend servido por Nginx;
- PHP-FPM no expuesto públicamente;
- un único servicio público por subdominio;
- healthchecks;
- límites de recursos documentados, no codificados como valores irreversibles;
- comando de release con lock para migraciones;
- rollback mediante imagen etiquetada con SHA.

## GitHub Actions

Workflow para pull requests y `main`:

### Backend

```text
composer validate
composer install
vendor/bin/pint --test
vendor/bin/phpstan analyse
php artisan test
```

Usar servicios MySQL 8.4 y Redis.

### Frontend

```text
npm ci
npm run lint
npm run typecheck
npm run test -- --run
npm run build
```

### Contenedores

- construir imágenes de API y web;
- no publicar imágenes desde un PR;
- en `main`, dejar preparado el etiquetado por SHA.

## Comandos de desarrollador

El `Makefile` debe incluir como mínimo:

```text
make setup
make up
make down
make logs
make migrate
make test
make lint
make build
make shell-api
make shell-web
```

## Fuera de alcance

No implementar:

- tablas de loterías;
- consumo de la API externa;
- métodos;
- señales;
- perfiles de pago;
- capital;
- backtests;
- palés;
- notificaciones;
- Filament;
- SSR;
- WebSockets.

## Criterios de aceptación

- [ ] Repositorio usa la estructura fijada.
- [ ] `make setup` completa una instalación limpia.
- [ ] `make up` inicia todos los servicios locales.
- [ ] API health responde JSON y comprueba MySQL/Redis.
- [ ] Propietario puede crearse por Artisan.
- [ ] Login/logout/me funcionan con Sanctum.
- [ ] SPA protege el dashboard.
- [ ] Interfaz es usable en móvil.
- [ ] Horizon funciona.
- [ ] Scheduler registra heartbeat.
- [ ] Backend lint, análisis y pruebas pasan.
- [ ] Frontend lint, typecheck, pruebas y build pasan.
- [ ] E2E mínima pasa.
- [ ] Imágenes Docker compilan.
- [ ] No hay secretos.
- [ ] Documentación de instalación y Dokploy está actualizada.
- [ ] No se adelantó lógica de fases futuras.

## Entrega esperada de Codex

- Rama: `codex/phase-00-foundation`.
- Un único pull request hacia `main`.
- Resumen de arquitectura.
- Lista de comandos ejecutados y resultados.
- Capturas del login y dashboard si el entorno lo permite.
- Riesgos pendientes claramente indicados.
