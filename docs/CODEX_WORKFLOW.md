# Flujo de trabajo con Codex

## Regla principal

No pedirle a Codex que construya toda la aplicación en una sola tarea.

Cada fase se divide en issues pequeñas que terminan en un PR verificable.

## Flujo

1. Crear issue en GitHub.
2. Especificar objetivo, alcance y aceptación.
3. Asignar la tarea a Codex.
4. Codex lee `AGENTS.md` y documentos.
5. Crea una rama.
6. Implementa.
7. Ejecuta pruebas.
8. Abre PR.
9. Se revisa el diff y la evidencia.
10. Se hace merge.
11. Dokploy despliega la rama configurada.

## Tamaño recomendado de tareas

Correcto:

```text
Crear migraciones y modelos de lotteries y draws.
```

Demasiado grande:

```text
Construye toda la app de loterías.
```

## Plantilla de prompt

```text
Lee AGENTS.md y estos documentos:
- docs/ARCHITECTURE.md
- docs/DOMAIN_RULES.md
- docs/ROADMAP.md

Implementa únicamente: <issue>.

Antes de programar:
1. Resume el alcance.
2. Identifica riesgos.
3. Enumera los archivos que esperas modificar.

Requisitos:
- No cambies decisiones de arquitectura sin documentarlo.
- No uses datos futuros en cálculos.
- Añade pruebas.
- Ejecuta lint, tests y build.
- No incluyas secretos.
- Actualiza documentación afectada.

Al finalizar:
- Resume cambios.
- Incluye comandos y resultados de pruebas.
- Indica cualquier limitación.
```

## Orden inicial de issues

```text
#1 Bootstrap del monorepo.
#2 Docker local y producción.
#3 CI de backend y frontend.
#4 Health endpoints.
#5 Modelo de lotteries y schedules.
#6 Modelo de draws.
#7 Cliente de API externa.
#8 Job idempotente de sincronización.
#9 Pantalla de estado de sincronización.
#10 Registro de operadores.
```

## Revisión humana obligatoria

Antes de merge:

- Revisar migraciones.
- Confirmar que no haya secretos.
- Confirmar que las fórmulas respeten módulo 100.
- Revisar horarios.
- Confirmar que las pruebas no utilicen el futuro.
- Revisar cambios en Docker y variables.
