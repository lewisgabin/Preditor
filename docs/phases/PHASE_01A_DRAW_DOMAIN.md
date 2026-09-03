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
ni cabeceras de autorización. Fase 1B aplica sanitización recursiva antes de
persistir payloads, cuarentenas, errores o cuerpos no JSON: la clave en ruta y
cualquier secreto se representan como `[REDACTED]`.

La ingesta de Fase 1B conserva esta frontera: el proveedor real solo consulta el
sorteo actual por lotería y los comandos son manuales. No añade scheduler,
polling, interfaz React ni lógica de Fase 1C.
