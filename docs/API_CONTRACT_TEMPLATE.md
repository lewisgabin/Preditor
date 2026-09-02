# Contrato pendiente de la API externa

Este documento se completa antes de implementar la sincronización.

## Información requerida

```text
Base URL:
Autenticación:
Header o query param:
Límites por minuto:
Endpoint de últimos sorteos:
Endpoint por lotería:
Endpoint por fecha:
Zona horaria:
Puede corregir resultados:
Identificador único del sorteo:
```

## Payload esperado como referencia

La aplicación debe adaptar el payload real mediante un DTO. No se debe asumir que este ejemplo es exacto.

```json
{
  "id": 123456,
  "loteria_id": 5,
  "fecha_sorteo": "2026-09-01",
  "hora": "20:55:00",
  "premios": "12-34-56"
}
```

También se aceptará un formato separado:

```json
{
  "id": 123456,
  "lottery_id": 5,
  "drawn_at": "2026-09-02T00:55:00Z",
  "first": "12",
  "second": "34",
  "third": "56"
}
```

## Requisitos del cliente

- Timeout configurable.
- Retry con backoff para errores temporales.
- No reintentar indefinidamente errores 4xx.
- Rate limit.
- Logs sin exponer token.
- Idempotencia.
- Validación estricta de `00`–`99`.
- Guardar payload original.
- Cuarentena para datos inválidos.
- Métricas de última sincronización correcta.
- Comando manual para sincronizar una fecha o lotería.

## Estrategia inicial de polling

- Tarea general cada minuto.
- Consultar solamente loterías próximas a sortearse o pendientes.
- Aumentar frecuencia durante una ventana corta posterior al horario.
- Detener consultas repetidas cuando el sorteo quede confirmado.
- Ejecutar una reconciliación nocturna de los últimos días.
