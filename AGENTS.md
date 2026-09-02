# Instrucciones globales para Codex

Lee este archivo y todos los documentos de `docs/` antes de modificar código.

## Reglas de trabajo

1. Implementa únicamente el alcance de la issue asignada.
2. No hagas push directo a `main`; trabaja en una rama y prepara un PR.
3. No introduzcas secretos, tokens, URLs privadas ni credenciales.
4. Toda función de dominio importante debe tener pruebas.
5. No uses datos posteriores a la fecha de una señal para calcularla.
6. No uses `eval`, código PHP dinámico ni expresiones arbitrarias almacenadas por usuarios.
7. Las transformaciones se implementan mediante operadores permitidos y probados.
8. Todo proceso de importación y liquidación debe ser idempotente.
9. Un sorteo confirmado no se modifica silenciosamente. Las correcciones generan auditoría.
10. Los montos se guardan como enteros en centavos, nunca como `float`.
11. Los números de lotería se guardan como cadenas de dos caracteres: `00` a `99`.
12. La zona horaria del dominio es `America/Santo_Domingo`; la base puede persistir instantes en UTC.
13. Una versión de método utilizada en una señal es inmutable.
14. Una señal debe registrar exactamente los sorteos fuente utilizados.
15. Las señales vencidas nunca se recalculan utilizando resultados nuevos.
16. Los resultados de backtest deben incluir parámetros, rango de fechas, versión del método y perfil de pagos.
17. Un PR no está listo si lint, pruebas o build fallan.

## Stack fijado

- Backend: PHP 8.4 + Laravel 13.
- API: REST JSON versionada bajo `/api/v1`.
- Autenticación SPA: Laravel Sanctum.
- Cola y caché: Redis + Laravel Horizon.
- Base de datos: MySQL 8.4.
- Frontend: React 19.2 + TypeScript + Vite 8.2.
- Datos remotos: TanStack Query.
- Componentes: shadcn/ui mantenidos como código del proyecto.
- Estilos: Tailwind CSS 4.3.3 mediante `@tailwindcss/vite` 4.3.3.
- Gráficas: Apache ECharts.
- Backend tests: Pest.
- Frontend tests: Vitest + Testing Library.
- E2E: Playwright.
- Infraestructura: Docker Compose, GitHub Actions y Dokploy.

## Convenciones

- Código, clases, tablas y endpoints en inglés.
- Textos de interfaz y documentación de negocio en español.
- Controladores delgados.
- Casos de uso en `Application`.
- Reglas de negocio en `Domain`.
- Integraciones externas en `Infrastructure`.
- No acceder directamente al cliente HTTP desde controladores.
- No colocar fórmulas de métodos en componentes React.
- Usar tokens semánticos de shadcn/ui y Tailwind; evitar colores arbitrarios en componentes.
- La API es la fuente de verdad; React solo presenta y solicita acciones.

## Calidad mínima por PR

```text
Backend:
composer validate
vendor/bin/pint --test
php artisan test

Frontend:
npm run lint
npm run typecheck
npm run test
npm run build

E2E cuando corresponda:
npm run test:e2e
```

El PR debe incluir:

- Resumen.
- Decisiones tomadas.
- Migraciones creadas.
- Pruebas ejecutadas.
- Riesgos y trabajo pendiente.

## Code Review Rules

### Alcance

- Rechazar lógica de sorteos, métodos, señales, capital o backtesting dentro de la Fase 0.
- Rechazar dependencias añadidas sin uso inmediato y documentado.

### Seguridad

- Rechazar secretos, tokens, contraseñas o URLs privadas.
- Rechazar logs de cookies, credenciales o cabeceras de autorización.
- Rechazar CORS con `*` cuando se usan credenciales.

### Persistencia y tiempo

- Rechazar montos de dinero representados por `float`.
- Rechazar fechas de negocio sin tratamiento explícito de `America/Santo_Domingo`.

### Calidad

- Rechazar un PR con pruebas, lint, typecheck o build fallidos.
- Rechazar Dockerfiles que ejecuten procesos como root en producción sin justificación.
- Rechazar migraciones automáticas concurrentes desde web, worker y scheduler.
