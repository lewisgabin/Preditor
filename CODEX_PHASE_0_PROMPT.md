# Prompt para iniciar la Fase 0 en Codex

```text
Trabaja en el repositorio QuinielaLab y ejecuta exclusivamente la Fase 0.

Antes de editar:
1. Lee AGENTS.md completo.
2. Lee docs/PRODUCT_SPEC.md, docs/ARCHITECTURE.md,
   docs/DOMAIN_RULES.md, docs/DOKPLOY_DEPLOYMENT.md y
   docs/phases/PHASE_00_FOUNDATION.md.
3. Inspecciona el repositorio y resume las restricciones críticas.
4. Crea o utiliza la rama codex/phase-00-foundation.

Implementa todos los entregables y criterios de aceptación de:

docs/phases/PHASE_00_FOUNDATION.md

Decisiones ya tomadas:
- monorepo con apps/api y apps/web;
- Laravel 13.x sobre PHP 8.4;
- API REST /api/v1;
- React 19.2 + TypeScript + Vite 8.2.x;
- shadcn/ui CLI 4.19.1, Tailwind CSS 4.3.3 y TanStack Query;
- MySQL 8.4;
- Redis + Horizon;
- Sanctum stateful SPA;
- Docker Compose local y producción compatible con Dokploy;
- sin SSR;
- sin Axios;
- sin registro público;
- zona America/Santo_Domingo.

No implementes ninguna lógica de lotería todavía.
No inventes el contrato de la API de sorteos.
No incluyas secretos ni credenciales reales.
No sustituyas silenciosamente MySQL por SQLite en pruebas de integración.

Trabaja de forma verificable:
- crea pruebas durante la implementación;
- ejecuta toda la suite;
- corrige los fallos;
- construye las imágenes Docker;
- revisa que .env.example no tenga secretos;
- actualiza README y documentación;
- comprueba que un clon limpio puede instalarse con los comandos documentados.

Al terminar:
1. muestra el árbol principal creado;
2. resume cambios y decisiones;
3. lista migraciones y endpoints;
4. incluye los comandos y resultados de pruebas;
5. enumera las variables de entorno;
6. señala riesgos pendientes;
7. abre un pull request hacia main con el título:
   feat: complete phase 0 foundation
```
