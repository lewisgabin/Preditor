# Corrección E2E de Fase 0

## Problema

El job `containers-and-e2e` construye las imágenes, inicia MySQL y Redis, migra,
levanta el stack, crea el propietario y supera el health check. Playwright abre
`http://127.0.0.1:5173`, pero el Compose local configura `SESSION_DOMAIN=localhost`
y la allowlist stateful de Sanctum solo contiene hosts `localhost`. La cookie de
sesión emitida para `localhost` no es válida para el host `127.0.0.1`, por lo que
el login no conserva una sesión autenticada y la SPA permanece en `/login`.

El workflow también exporta la contraseña efímera mediante `GITHUB_ENV` sin
enmascararla antes. Aunque el usuario y su volumen se eliminan al finalizar, la
contraseña no debe aparecer en logs.

## Enfoques considerados

1. **Cookie local host-only y allowlists para ambos hosts (seleccionado).** Dejar
   `SESSION_DOMAIN` vacío en Compose local y permitir explícitamente `localhost`
   y `127.0.0.1` en Sanctum/CORS. Funciona con el README y con Playwright sin
   alterar el contrato de producción.
2. **Cambiar Playwright a `localhost`.** Es un cambio menor, pero oculta que el
   puerto publicado también es accesible como `127.0.0.1` y mantiene una
   configuración local frágil.
3. **Sobrescribir dominios solo en CI.** Aísla el runner, pero deja el mismo fallo
   disponible para desarrolladores que usan `127.0.0.1`.

## Diseño aprobado

### Compose local

- Emitir cookies host-only por defecto, sin fijarlas a `localhost`.
- Definir exactamente `SANCTUM_STATEFUL_DOMAINS` con
  `localhost:5173,127.0.0.1:5173,localhost:5174,127.0.0.1:5174,localhost:8080,127.0.0.1:8080`.
- Definir exactamente `CORS_ALLOWED_ORIGINS` con
  `http://localhost:5173,http://127.0.0.1:5173,http://localhost:5174,http://127.0.0.1:5174`;
  nunca usar `*` con credenciales.
- Permitir sobrescribir ambas listas por variables de entorno. `WEB_PORT=5173` y
  `WEB_PORT=5174` quedan cubiertos por defecto; cualquier otro puerto exige que el
  operador amplíe ambas variables. `E2E_BASE_URL` debe señalar el mismo host y
  puerto publicado por `WEB_PORT`.
- No modificar `docker-compose.prod.yml`: producción continúa exigiendo
  `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS` y `CORS_ALLOWED_ORIGINS`.

### Workflow

- Generar una contraseña distinta por ejecución.
- Enmascararla con el comando de workflow antes de exportarla a `GITHUB_ENV`.
- Mantener la entrega por stdin al comando `app:create-owner`.
- Verificar por condición la salud de la SPA y de `/api/health` antes del login.
- Comprobar CORS contra la API con orígenes `http://localhost:5173` y
  `http://127.0.0.1:5173`: cada respuesta debe devolver su origen exacto y
  `Access-Control-Allow-Credentials: true`. Un origen no autorizado no debe recibir
  `Access-Control-Allow-Origin`.

### Playwright

- Mantener el puerto por defecto `5173` y respetar `E2E_BASE_URL`.
- Antes del login, comprobar que la SPA responde y que `/api/health` informa
  aplicación, MySQL y Redis saludables.
- Conservar el flujo real de Sanctum: adquirir CSRF, enviar credenciales, recibir
  cookies de sesión, navegar a `/` y consultar `/api/v1/auth/me` autenticado.
- Exigir `E2E_EMAIL` y `E2E_PASSWORD` con un error inmediato y descriptivo; no
  omitir la prueba cuando falten.
- Comprobar la existencia de `XSRF-TOKEN` tras adquirir CSRF y de la cookie de
  sesión tras el login, sin registrar sus valores.
- Conservar todas las aserciones actuales y añadir comprobaciones de cookie/CSRF;
  no usar `test.skip`, `continue-on-error` ni esperas fijas.
- Fallar si el navegador registra errores de consola inesperados.
- Usar sondeos condicionados con timeout acotado para salud y navegación; el
  intervalo interno del sondeo no sustituye la condición por un `sleep` fijo.

## Compatibilidad

El cambio solo ajusta defaults de desarrollo/CI. Los valores de producción siguen
siendo obligatorios y específicos del dominio HTTPS. No se añaden dependencias,
migraciones ni módulos de loterías, sorteos, métodos, señales o capital.

## Verificación

Se ejecutarán todas las puertas backend y frontend, ambos builds Docker, el stack
integrado con `docker compose up -d --wait`, el E2E real y el pipeline remoto. La
entrega solo se considera lista cuando `backend`, `frontend` y
`containers-and-e2e` estén verdes en GitHub Actions.
