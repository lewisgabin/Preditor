# Roadmap por fases

## Fase 0 — Definición y repositorio

### Objetivo

Crear una base reproducible para humanos, Codex, GitHub Actions y Dokploy.

### Entregables

- Monorepo.
- Laravel y React iniciales.
- Docker local.
- Docker de producción.
- `.env.example`.
- Health checks.
- GitHub Actions.
- `AGENTS.md`.
- Convenciones.
- Protección de `main`.

### Aceptación

- El entorno inicia con un comando.
- `/api/health` responde.
- El frontend muestra el estado de la API.
- Lint, tests y build pasan.
- No existen secretos en Git.

---

## Fase 1 — Catálogo e ingesta de sorteos

### Objetivo

Consumir la API existente de forma confiable.

### Entregables

- Tablas `lotteries`, `lottery_schedules`, `draws`, `sync_runs`, `sync_errors`.
- Seeder de IDs existentes.
- Cliente HTTP tipado.
- DTO y normalizador.
- Jobs de polling.
- Scheduler.
- Idempotencia.
- Reconciliación manual.
- Pantalla de estado de sincronización.

### Aceptación

- Repetir la misma respuesta de la API no crea duplicados.
- `00` conserva el cero.
- Un payload inválido va a cuarentena.
- Los errores temporales se reintentan.
- Hay pruebas con fixtures de la API real.

---

## Fase 2 — Motor de métodos

### Objetivo

Implementar métodos configurables y versionados.

### Entregables

- `methods`, `method_versions`, `signals`, `signal_sources`.
- Registro de operadores permitidos.
- Diez métodos principales P01–P10 y diez alternativas A01–A10.
- Explicación humana del cálculo.
- Generación explícita mediante API y Artisan, sin API externa.
- Señales históricas vencidas y corte temporal por horario verificable.
- Pantallas protegidas `/metodos` y `/senales`.

### Aceptación

- La misma entrada produce siempre la misma señal.
- No puede utilizarse un sorteo posterior.
- Cambiar un método crea una versión.
- Señales históricas conservan su versión original.
- Cada operador posee pruebas con casos `00`, negativos y módulo 100.

---

## Fase 3 — Liquidación y perfiles de pago

### Objetivo

Calcular automáticamente resultados y pagos.

### Entregables

- Perfiles de pago editables.
- Liquidadores de quiniela.
- Estado ganador por posición.
- Auditoría de correcciones.
- Liquidación idempotente.
- API de resultados.

### Aceptación

- 70x/8x/4x funciona.
- El pago puede cambiarse sin alterar liquidaciones previas.
- Una señal no puede pagarse dos veces.
- Una corrección genera reverso y nueva liquidación.

---

## Fase 4 — Backtesting mensual

### Objetivo

Reproducir estrategias históricas de forma verificable.

### Entregables

- Cola de backtests.
- Rango de fechas.
- Resultado día por día y mes por mes.
- ROI, drawdown, rachas, hit rate.
- Comparación entrenamiento/prueba.
- Exportación CSV.
- Gráficas.

### Aceptación

- Un backtest guarda todos sus parámetros.
- El resultado puede reproducirse.
- Las pruebas impiden look-ahead.
- Un método puede compararse con una base aleatoria.

---

## Fase 5 — Capital y ciclos

### Objetivo

Implementar el plan RD$20,000.

### Entregables

- Bankroll plan.
- Ciclo mensual.
- Reserva.
- Billetera.
- Meta.
- Stop-loss.
- Piso duro.
- Exposición diaria.
- Ledger.
- Semáforo mensual.
- Bloqueo de beneficios.

### Aceptación

- Se detienen recomendaciones monetarias al tocar stop.
- Al tocar meta se cierra el ciclo.
- No existe martingala automática.
- Cada peso se explica mediante el ledger.
- Los montos usan enteros en centavos.

---

## Fase 6 — Interfaz MVP

### Objetivo

Hacer la aplicación usable diariamente desde móvil.

### Entregables

- Login.
- Dashboard.
- Señales de hoy.
- Ciclo actual.
- Métodos.
- Historial mensual.
- Sincronización.
- Configuración de pagos.
- Diseño responsive.

### Aceptación

- Se usa correctamente desde un teléfono.
- Estados vacíos y errores son claros.
- No se muestran datos viejos como si fueran actuales.
- La interfaz muestra hora de última sincronización.

---

## Fase 7 — Palé

### Objetivo

Estudiar palés sin mezclar su riesgo con la quiniela.

### Entregables

- Señales de dos números.
- Liquidador 1–2, 1–3 y 2–3.
- Fondo independiente.
- Backtest de palés.
- Restricción de máximo diario.
- Perfil 2000x/2000x/100x editable.

### Aceptación

- Ambos números deben pertenecer al mismo sorteo destino.
- El orden se aplica según la regla de la banca.
- No se consume la reserva protegida.
- El palé puede apagarse globalmente.

---

## Fase 8 — Alertas y PWA

### Objetivo

Reducir trabajo manual.

### Entregables

- PWA instalable.
- Notificación de nueva señal.
- Aviso de resultado.
- Aviso de meta/stop.
- Recordatorio de API atrasada.
- Preferencias.

---

## Fase 9 — Producción y validación

### Objetivo

Desplegar una versión estable y comenzar el período futuro.

### Entregables

- Staging.
- Producción.
- Backups.
- Logs.
- Monitoreo.
- Rate limiting.
- Runbook.
- Plan de recuperación.
- Congelación del portafolio V1.

### Aceptación

- Restore de backup probado.
- Health checks visibles.
- Deploy reversible.
- `main` protegida.
- Dokploy despliega tras merge exitoso.
- El primer mes futuro queda separado del histórico.
