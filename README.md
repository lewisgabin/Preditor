# QuinielaLab — plan inicial

Aplicación web privada para:

- Consumir resultados de loterías desde una API externa.
- Generar señales mediante métodos versionados.
- Liquidar automáticamente quinielas y palés.
- Simular y controlar ciclos de capital.
- Analizar resultados por día, mes, método y portafolio.
- Validar reglas nuevas sin mezclar datos futuros con datos de entrenamiento.

> Estado: Fase 0 preparada para implementación en Codex. El nombre `QuinielaLab` es provisional.

## Principio central

La aplicación no debe prometer ganancias ni colocar apuestas automáticamente. Su función es calcular, registrar, simular y auditar estrategias con reglas reproducibles.

## Arquitectura

Monorepo:

```text
apps/
  api/       Laravel
  web/       React
infra/
  docker/
docs/
.github/
AGENTS.md
README.md
```

Servicios de producción:

```text
web             React compilado y servido por Nginx
api-nginx       entrada HTTP para Laravel
api             PHP-FPM
horizon         procesamiento de colas
scheduler       tareas programadas
mysql           administrado desde Dokploy
redis           administrado desde Dokploy
```

## Fases

1. Base del repositorio e infraestructura.
2. Sincronización de sorteos.
3. Motor de métodos y señales.
4. Backtesting y análisis mensual.
5. Capital, ciclos, meta y stop-loss.
6. Interfaz principal.
7. Módulo de palé.
8. Alertas, PWA y validación futura.
9. Seguridad, pruebas y producción.

La especificación completa está en `docs/ROADMAP.md`.

## Inicio de Fase 0

- Especificación: `docs/phases/PHASE_00_FOUNDATION.md`
- Issue lista para copiar: `ISSUE_001_PHASE_0.md`
- Prompt de Codex: `CODEX_PHASE_0_PROMPT.md`
- Configuración del repositorio: `REPOSITORY_SETUP.md`
- Verificación al terminar: `scripts/verify-phase0.sh`
