# Esquema de base de datos propuesto

## Identidad y configuración

### users
Usuario de la aplicación.

### payout_profiles
Perfil de una banca o escenario de simulación.

### payout_rules
Multiplicadores por tipo de jugada y posiciones.

## Loterías y sorteos

### lotteries
- id
- external_id
- name
- slug
- timezone
- active

### lottery_schedules
- lottery_id
- weekday
- draw_time
- effective_from
- effective_to

### draws
- lottery_id
- external_draw_id
- draw_date
- drawn_at
- p1
- p2
- p3
- status
- source_hash
- raw_payload
- received_at
- confirmed_at

Índices:

- unique `(lottery_id, external_draw_id)` cuando exista.
- unique `(lottery_id, drawn_at)` como respaldo.
- index `(lottery_id, draw_date)`.

### draw_corrections
Auditoría de correcciones.

### sync_runs
Ejecuciones del cliente de la API.

### sync_errors
Errores, payload y reintentos.

## Motor de métodos

### methods
Identidad estable del método.

### method_versions
Configuración inmutable.

Campos principales:

- method_id
- version
- source_lottery_id
- target_lottery_id
- source_timing
- operator_code
- operator_parameters JSON
- eligible_weekdays JSON
- payout_profile_id
- effective_from
- effective_to
- status

### portfolios
Grupo de métodos.

### portfolio_method_versions
Métodos y montos dentro de un portafolio.

### signals
- method_version_id
- target_lottery_id
- target_drawn_at
- predicted_number
- predicted_number_2 nullable
- stake_cents
- status
- generated_at
- expires_at
- settled_at
- explanation

### signal_sources
Relación exacta entre señal y sorteos fuente.

### settlements
Resultado y pago calculado.

## Capital

### bankroll_plans
Configuración versionada de capital.

### cycles
Instancia mensual o manual.

### cycle_limits
Meta, stop, piso y exposición.

### ledger_entries
Libro inmutable:

- cycle_id
- signal_id nullable
- type
- amount_cents
- occurred_at
- metadata

Tipos:

```text
stake
payout
profit_locked
deposit
withdrawal
adjustment
```

### daily_snapshots
Resumen diario para consultas rápidas.

## Backtests

### backtests
Solicitud y parámetros.

### backtest_runs
Estado del trabajo.

### backtest_signals
Señales simuladas reproducibles.

### backtest_metrics
Métricas generales y mensuales.

## Palés

Puede reutilizarse `signals` con `predicted_number_2`, pero la liquidación debe delegarse a una estrategia específica de `bet_type = pale`.
