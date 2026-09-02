# Primera tarea para Codex

Copia este texto en Codex después de crear y conectar el repositorio.

```text
Lee AGENTS.md y todos los archivos de docs/.

Implementa solamente la Fase 0 del roadmap.

Objetivo:
crear la base del monorepo QuinielaLab sin implementar todavía métodos,
sincronización de sorteos, backtests ni capital.

Backend:
- apps/api con Laravel 13 y PHP 8.4.
- API versionada.
- Laravel Sanctum instalado y configurado para una SPA.
- Endpoint GET /api/health.
- Pest.
- Pint.
- PHPStan/Larastan.
- .env.example sin secretos.

Frontend:
- apps/web con React 19.2, TypeScript y Vite 8.2.
- shadcn/ui.
- Tailwind CSS 4.3.3 con `@tailwindcss/vite` 4.3.3.
- TanStack Query.
- Página inicial que consulte /api/health.
- ESLint.
- Vitest.
- Typecheck.
- .env.example.

Infraestructura:
- Docker para desarrollo.
- Dockerfiles de producción.
- Docker Compose con web, api-nginx, api, horizon y scheduler.
- MySQL y Redis locales para desarrollo.
- La configuración de producción debe aceptar MySQL y Redis externos de Dokploy.
- Health checks.
- Makefile o comandos equivalentes.
- README con instalación.

GitHub:
- workflow CI para backend y frontend.
- Dependabot para Composer y npm.
- plantilla de pull request.

Restricciones:
- No implementes todavía tablas de loterías.
- No inventes el contrato de la API externa.
- No añadas credenciales.
- No uses SQLite como sustituto silencioso de MySQL en las pruebas de integración.
- No hagas cambios fuera del alcance sin explicarlos.

Criterios de aceptación:
- El proyecto inicia localmente con un comando documentado.
- GET /api/health devuelve JSON correcto.
- La SPA muestra si la API está disponible.
- Pruebas, lint, typecheck y build pasan.
- Los contenedores tienen health checks.
- La documentación permite configurar Dokploy posteriormente.

Al terminar:
1. Muestra el árbol creado.
2. Resume decisiones.
3. Enumera variables de entorno.
4. Incluye salida de las pruebas.
5. Señala cualquier bloqueo real.
```
