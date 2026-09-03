# Fase 1B — Proveedor idempotente de sorteos

## Alcance entregado

Fase 1B integra proveedores de sorteos sobre el dominio de Fase 1A. Incluye
contratos tipados, normalización, provider fake determinista, adaptador HTTP del
provider real, ejecuciones de sincronización, cuarentena, correcciones auditables,
cola y comandos Artisan manuales.

No incluye scheduler automático, polling, interfaz React de sincronización,
métodos, señales, pagos, capital, backtesting, palés ni notificaciones. El
scheduler de infraestructura de Fase 0 no agenda sorteos: esa automatización
corresponde a Fase 1C o una entrega posterior aprobada.

## Providers y capacidades

| Provider | Red | Actual | Fecha | Rango/reconciliación |
| --- | --- | --- | --- | --- |
| `fake` | No | Sí | Sí | Sí |
| `elboletoganador` | Sí, configurable | Sí | No | No |

El real recibe `--lottery` como `lotteries.external_id`. Solo consulta el resultado
actual y rechaza fecha, rango o reconciliación antes de crear un `SyncRun`,
encolar un job o hacer HTTP. `--force` permite invocación manual cuando
`LOTTERY_API_ENABLED=false`, pero no elude capacidades, validación, locks ni
reintentos.

La respuesta real aceptada es un objeto directo sanitizado con `id`, `loteria_id`,
`fecha_sorteo`, `premios` y, opcionalmente, `hora`. `id` se guarda como
`external_draw_id`; `premios` se valida como tres cadenas `00`–`99`; una hora
válida se interpreta en `America/Santo_Domingo` y se convierte a UTC.

Un `null`, lista vacía o sorteo anterior a la fecha solicitada/actual es
`not_available`: no es fallo ni cuarentena; el run termina `succeeded` con
`metadata.result_pending=true`. Un envoltorio, lista no vacía o payload inválido
entra a cuarentena.

## Seguridad y trazabilidad

La clave se configura fuera de Git y se usa solo en memoria. Como el provider real
la coloca en la ruta, toda representación persistida o emitida la sustituye por
`[REDACTED]`. Nunca se guardan ni exponen clave, token, URL original,
`Authorization`, cookies o cabeceras sensibles en `raw_payload`, cuarentenas,
errores, metadata, logs, tags de Horizon, excepciones o recursos.

Los payloads inválidos no generan sorteos parciales. Se almacenan sanitizados en
`draw_quarantines` con código, errores de validación, ejecución y lotería si se
puede identificar. Una lotería remota desconocida no se crea automáticamente.

## Idempotencia y correcciones

Cada persistencia usa transacción y bloqueo de fila. La identidad principal es
`(lottery_id, provider, external_draw_id)` y fecha/hora programada es respaldo.
Repetir una identidad con el mismo hash material incrementa `items_unchanged` sin
modificar la fila. Un hash distinto crea primero un registro append-only en
`draw_corrections`, actualiza el sorteo y lo marca `corrected`.

Un lock Redis evita sincronizaciones concurrentes del mismo provider, lotería y
alcance. Los índices únicos mantienen la idempotencia si el lock expira. Si dos
identidades recuperadas apuntan a sorteos distintos, no se escoge ni modifica una
fila: se registra error de persistencia, el payload va a cuarentena y el run queda
`failed` o `partial` según existan efectos previos.

## Ejecución manual

```bash
# Prueba local sin red: consulta y normaliza, pero no persiste ni reintenta.
docker compose exec api php artisan draws:sync --provider=fake --lottery=5 --dry-run

# Encola una consulta actual con el provider real ya configurado.
docker compose exec api php artisan draws:sync --provider=elboletoganador --lottery=5

# El fake admite historial de prueba y reconciliación manual.
docker compose exec api php artisan draws:reconcile --provider=fake --lottery=5 --from=YYYY-MM-DD --to=YYYY-MM-DD
```

`--dry-run` crea un `SyncRun` con `dry_run=true`, incrementa solo
`items_received`, no escribe sorteos/correcciones/cuarentenas, no emite eventos y
no reintenta. Disponible y pendiente terminan exitosamente; un payload inválido o
fallo permanente deja un error sanitizado y termina fallido.

El job usa la cola `draw-sync`, un único UUID de `SyncRun` y contadores acumulados
entre intentos. Reintenta de forma acotada red, timeout y HTTP 408, 429, 500, 502,
503 y 504; para 429 usa `Retry-After` si está disponible. No reintenta 400, 401,
403, 404 contractual ni 422. Un fallo terminal queda `failed`, o `partial` cuando
ya hubo persistencias o cuarentenas previas.

Los eventos de confirmación, corrección, cuarentena y finalización se despachan
después del commit. Sus consumidores futuros deben ser idempotentes; Fase 1B no
promete entrega exactamente una vez ni instala listeners de fases posteriores.
