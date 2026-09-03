# Contrato de proveedor externo de sorteos

Fase 1B integra un proveedor configurable para consultar solamente el sorteo
actual de una lotería. No importa historial, no agenda polling y no incorpora UI.

## Información requerida

```text
Proveedor: El Boleto Ganador
Base URL: configurada fuera de Git mediante LOTTERY_API_BASE_URL
Autenticación: clave como segmento de ruta, nunca header Authorization
Endpoint actual: GET /api/sorteos/[REDACTED]/{lottery_id}
Lotería: lotteries.external_id
Zona horaria: America/Santo_Domingo
Identificador único: id remoto → external_draw_id
Capacidades reales: solo sorteo actual por lotería
No soportado: fechas históricas, rangos y reconciliación
```

## Payload esperado como referencia

La aplicación debe adaptar el payload real mediante un DTO. No se debe asumir que este ejemplo es exacto.

```json
{
  "id": 123456,
  "loteria_id": 5,
  "fecha_sorteo": "YYYY-MM-DD",
  "hora": "HH:MM:SS",
  "premios": "12-34-56"
}
```

Es un objeto directo, no una lista ni un envoltorio. Antes de conservarlo se
sanitiza recursivamente: claves, tokens, cookies, cabeceras de autorización y
segmentos sensibles se sustituyen por `[REDACTED]`.

## Mapeo y resultado pendiente

- `id` se persiste como `external_draw_id`.
- `loteria_id` debe coincidir con el `external_id` solicitado.
- `fecha_sorteo` es fecha local en `America/Santo_Domingo`.
- `hora` válida compone el instante UTC; si falta o es inválida, se conserva la
  identidad externa sin inventar hora.
- `premios` debe tener exactamente tres valores `00`–`99`; `04-00-97` conserva
  los ceros iniciales.
- Un cuerpo `null` o lista vacía es `not_available`: el resultado está pendiente,
  el `SyncRun` termina exitoso y registra `result_pending=true`.
- Una lista no vacía, envoltorio u objeto inválido no es pendiente: va a
  cuarentena con contexto sanitizado.

El provider `fake` es determinista, no usa Internet y sí admite actual, fecha y
rango para pruebas.

## Payloads no contractuales

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

El formato anterior no está soportado por el proveedor real de Fase 1B; requiere
una ampliación aprobada del contrato.

## Requisitos del cliente

- Timeout y conexión configurables.
- Reintentos acotados con backoff para red, timeout y HTTP 408, 429, 500, 502,
  503 y 504; para 429 se respeta `Retry-After` cuando exista.
- Los 400, 401, 403, 404 contractual y 422 no se reintentan. Los 401/403 se
  clasifican como autenticación sin revelar la clave.
- Idempotencia por lotería, proveedor e identidad externa; fecha/hora es
  respaldo. Repetir el mismo hash no modifica el sorteo.
- Un hash material distinto crea primero una corrección append-only y deja el
  sorteo `corrected`.
- Un payload no normalizable no crea un sorteo parcial: va a cuarentena; una
  lotería remota desconocida no se crea automáticamente.

## Operación manual

```bash
docker compose exec api php artisan draws:sync --provider=fake --lottery=5 --dry-run
docker compose exec api php artisan draws:sync --provider=elboletoganador --lottery=5
```

El real exige `--lottery` y rechaza `--date`, `--from/--to` y
`draws:reconcile` antes de crear una ejecución, encolar un job o hacer HTTP.
`--force` solo permite invocación manual cuando está desactivado; no elude
capacidades, validación, locks ni límites de reintento. No hay scheduler,
polling, reconciliación real ni UI de sincronización en Fase 1B.
