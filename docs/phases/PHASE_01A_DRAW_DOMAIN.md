# Fase 1A: dominio de sorteos

Esta fase incorpora catálogo, persistencia y lectura autenticada. No realiza
llamadas de red, sincronización, scheduler, jobs, interfaz de sorteos, métodos,
señales, pagos, capital, backtesting ni palé.

## Horarios pendientes

No se siembran horarios. Antes de crear `lottery_schedules` reales se necesita,
por cada lotería, una fuente oficial con día de semana, hora local, hora de
cierre y rango de vigencia. Ningún horario se deduce del nombre de la lotería.

## Datos operativos sensibles

`raw_payload`, `metadata` y `safe_context` nunca pueden guardar tokens, cookies
ni cabeceras de autorización. La sanitización de respuestas de proveedores y sus
normalizadores pertenece a Fase 1B.
