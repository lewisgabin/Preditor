# Fase 1B: proveedor idempotente de sorteos

## Alcance

Esta fase integra una fuente de sorteos configurable sobre el dominio entregado
en Fase 1A. Incluye el contrato interno del proveedor, adaptador HTTP de El
Boleto Ganador, proveedor falso determinista, normalización, persistencia
idempotente, auditoría de correcciones, cuarentena, job manual, comandos y
eventos de trazabilidad.

No incluye scheduler automático, interfaz React, métodos, señales, pagos,
capital, backtesting, palés ni notificaciones. El endpoint real tampoco se usa
para importar historial: solo puede consultar el resultado actual por lotería.

## Contrato externo aprobado

| Aspecto | Valor |
| --- | --- |
| Base URL | `https://api.elboletoganador.com` |
| Método | `GET` |
| Endpoint | `/api/sorteos/{api_key}/{lottery_id}` |
| Autenticación | La clave es un segmento de ruta; no usa cabecera `Authorization` |
| Lotería | El parámetro es `lotteries.external_id` |
| Zona horaria | `America/Santo_Domingo` |
| Identidad | Campo remoto `id`, persistido como `external_draw_id` |
| Capacidades | Sorteo actual por lotería; sin fechas históricas ni rangos |

El formato observado de una respuesta disponible es un objeto directo como:

```json
{
  "id": 227821,
  "premios": "50-32-77",
  "numero_sorteo": "63419",
  "fecha_sorteo": "2026-08-31",
  "loteria_id": 4,
  "created_at": "2026-09-01T01:02:06.000000Z",
  "updated_at": "2026-09-01T01:02:06.000000Z",
  "loto1": null,
  "loto2": null,
  "hora": "21:01:31"
}
```

El objeto directo anterior es el contrato implementable de esta fase. Una
inspección local con una clave ya configurada lo verificará antes de activar el
provider real; la clave nunca se imprimirá ni se escribirá en archivos. Una lista
vacía representa resultado pendiente; una lista no vacía u otro envoltorio se
tratan como payload inválido, se envían a cuarentena y requieren ampliar el
contrato en una entrega posterior. Las fixtures y pruebas usan exclusivamente
este objeto sanitizado.

## Capas y contratos

- `Application/Draws/Data`: DTOs puros `DrawFetchRequest`, `DrawFetchResult` y
  `DrawProviderCapabilities`; no reciben modelos Eloquent.
- `Application/Draws/Contracts/LotteryDrawProvider`: puerto sin HTTP que recibe
  un `DrawFetchRequest` y devuelve un `DrawFetchResult` tipado.
- `Application/Draws`: DTOs de solicitud y resultado, normalizador, caso de uso
  `PersistNormalizedDraw`, orquestador `SyncLotteryDraws` y eventos de
  aplicación `DrawConfirmed`, `DrawCorrected`, `DrawQuarantined` y
  `DrawSyncCompleted`.
- `Infrastructure/Draws`: `FakeLotteryDrawProvider`,
  `HttpLotteryDrawProvider`, sanitizador de secretos y las dependencias Laravel
  HTTP/Redis/queue.
- `Http`: comandos Artisan; no se añade endpoint ni pantalla de sincronización.

Los controladores existentes de lectura no usan el cliente externo. El dominio
no conoce URL, clave, cabeceras, query parameters, JSON del proveedor ni el
cliente HTTP de Laravel.

## Solicitud, capacidades y resultado

`DrawFetchRequest` contendrá proveedor, `lotteryExternalId`, fecha opcional,
rango opcional y trigger. No contiene un modelo Eloquent. El request validará
que fecha y rango no se mezclen y que un rango sea creciente. Cada provider
declarará las capacidades de sorteo actual, fecha histórica y rango.

`DrawFetchResult` tendrá tres resultados excluyentes:

- `available`: uno o más payloads sanitizables;
- `not_available`: el endpoint reconoce una respuesta vacía, `null`, lista vacía
  o un resultado con `fecha_sorteo` anterior a la fecha solicitada/actual;
- `failure`: red o respuesta HTTP clasificada, con estado HTTP y contexto seguro.

Para El Boleto Ganador, `--date`, `--from/--to` y `draws:reconcile` se rechazan
antes de crear o encolar un trabajo, sin solicitud HTTP: no inventan capacidades
históricas. El fake sí permite esas modalidades para pruebas. `--lottery` siempre
recibe `lotteries.external_id` y es obligatorio para el provider real; si se
omite o no existe, el comando falla con un mensaje seguro. `--force` solo permite
una invocación manual cuando `LOTTERY_API_ENABLED=false`; nunca elude capacidades,
validación, locks ni límites de reintento. Un payload con fecha anterior no es
error, no entra a cuarentena y deja el `SyncRun` exitoso con
`metadata.result_pending=true`.

## Normalización y seguridad

`ProviderPayloadNormalizer` transforma el objeto externo a `NormalizedDrawData`:

- `id` a cadena `external_draw_id`;
- `loteria_id` a `lotteryExternalId`, que debe coincidir con la lotería pedida;
- `fecha_sorteo` a fecha local válida;
- `premios` separado exactamente en tres posiciones;
- cada premio pasa por `LotteryNumber`, preservando `04-00-97`;
- `hora`, si está presente y es válida, compone el instante local que se convierte
  a UTC; de lo contrario el sorteo conserva su identidad externa;
- antes de persistir, el payload JSON se sanitiza recursivamente y entonces se
  conserva como `raw_payload`; si el cuerpo no es JSON, se persiste una
  representación JSON acotada con tipo de contenido, longitud, hash y fragmento
  sanitizado/truncado;
- el hash SHA-256 se calcula sobre la proyección material canónica (proveedor,
  identidad, lotería, fecha/hora y `p1`/`p2`/`p3`), no sobre campos volátiles como
  `updated_at`.

Un payload inválido no crea sorteos parciales: se registra en
`draw_quarantines` con payload sanitizado, código, errores, ejecución y lotería
si puede identificarse. Loterías remotas desconocidas no se crean automáticamente.
La misma sanitización se aplica a `raw_payload`, cuarentenas y fragmentos de
cuerpos inválidos, incluso si el proveedor refleja una URL o clave dentro de JSON.

`ProviderSecretSanitizer` será la única vía para serializar URL, excepción o
contexto de proveedor. Reemplazará el segmento de clave por `[REDACTED]` y
eliminará cabeceras, cookies y tokens. El adaptador captura toda excepción del
cliente HTTP antes de que llegue a Laravel/Horizon y la convierte en un fallo
tipado; si el job debe reintentarse, lanza exclusivamente una excepción segura
sin URL ni cuerpo original. Nunca se persistirá ni expondrá la URL original,
clave, `Authorization` o cookies en errores, metadata, tags, logs, excepciones,
`failed_jobs` o Resources.

## Persistencia idempotente y correcciones

Cada elemento disponible se persiste en una transacción MySQL y se localiza por
`(lottery_id, provider, external_draw_id)`; si el proveedor no aportara ID, usa
la identidad de respaldo ya existente `(lottery_id, scheduled_at_utc)`.

| Caso | Efecto |
| --- | --- |
| Nuevo | Crea `Draw` confirmado y aumenta `items_inserted`. |
| Misma identidad y hash | No modifica nada y aumenta `items_unchanged`. |
| Misma identidad y hash distinto | Crea primero `DrawCorrection` append-only, actualiza el sorteo, hash y estado `corrected`, y aumenta `items_updated`. |
| No normalizable | Crea `DrawQuarantine` y aumenta `items_quarantined`. |

Las actualizaciones usan bloqueo de fila dentro de la transacción. Un lock Redis
con clave derivada de proveedor, lotería y alcance evita que dos jobs concurran;
la base y sus índices únicos conservan la idempotencia incluso si el lock expira.
Para la carrera de dos inserciones nuevas, una violación de índice único se
recupera dentro de una transacción acotada recargando por ambas identidades y
reaplicando la comparación de hash, en vez de marcar el job fallido. Si las dos
identidades recuperan sorteos distintos, se conserva cada fila sin alterarla,
se registra un `SyncError` de tipo `persistence` y el payload sanitizado entra a
cuarentena; aumenta `items_quarantined` y jamás se elige una fila arbitrariamente.
Si es el único elemento, el run termina `failed`; con efectos previos exitosos,
termina `partial`.

La escritura de sorteo/corrección/cuarentena y el incremento de su contador se
confirman en la misma transacción. Los eventos `DrawConfirmed`, `DrawCorrected`
y `DrawQuarantined` se despachan solo después del commit; no habrá listeners de
recomendaciones, pagos ni otras fases.

## Ejecuciones, fallos y reintentos

`SyncLotteryDraws` será un job de cola `draw-sync` con `tries`, `timeout` y
`backoff` explícitos; sus tags de Horizon no contienen secretos. El comando crea
un único `SyncRun` con UUID y estado `queued`; el UUID viaja en el payload del job.
Cada intento reutiliza esa ejecución, registra un `SyncError` con su número de
intento y la marca `running` sin reiniciar contadores. `not_available` termina
`succeeded` sin modificar contadores. Si todos los elementos disponibles se
persisten, termina `succeeded`; termina `partial` si existe cuarentena o algún
efecto exitoso anterior seguido de un error terminal; un error permanente o el
agotamiento de reintentos sin procesamiento exitoso termina `failed`. Un error
temporal de un intento anterior no impide `succeeded`/`partial` si un intento
posterior concluye. Se guardan HTTP status, duración y contadores acumulados una
sola vez por efecto persistido. `DrawSyncCompleted` se despacha después del
commit del estado terminal y sus futuros consumidores deben ser idempotentes; no
se promete entrega exactamente una vez sin un outbox, que queda fuera de fase.

Los fallos temporales son timeout/conexión y HTTP 408, 429, 500, 502, 503 y 504;
se clasifican en `SyncError` y se reintentan hasta el límite. El retraso de 429
respeta `Retry-After` cuando exista. HTTP 400, 401, 403, 404 contractual y 422
no se reintentan; 401/403 usan tipo `authentication`. La indisponibilidad de un
proveedor no afecta `/api/health`.

## Comandos y configuración

Se implementarán `draws:sync` y `draws:reconcile`; ambos reutilizan el mismo caso
de uso y validan capacidades antes de encolar. Aceptan `--lottery`, `--date`,
`--from`, `--to`, `--dry-run`, `--force` y `--provider`. `--dry-run` se ejecuta
sin cola, crea un `SyncRun` con metadata `dry_run=true`, consulta y normaliza,
pero no inserta ni actualiza `Draw`, corrección o cuarentena, no despacha eventos
y no reintenta. `items_received` refleja los payloads observados; los otros cuatro
contadores permanecen en cero. Un payload válido termina `succeeded`; un
`not_available` termina `succeeded` con `result_pending=true`; un payload inválido
o fallo permanente registra `SyncError` sanitizado y termina `failed`. Los
mensajes solo contienen información sanitizada.

La configuración incluye `LOTTERY_API_ENABLED`, proveedor, base URL, plantilla,
clave, timeouts, retry y lookback/reconciliación. La clave queda vacía en
`.env.example`; CI usa `FakeLotteryDrawProvider` y Dokploy solo acepta los
nombres de variables, sin valores secretos.

## Pruebas y compatibilidad

La suite Pest MySQL 8.4 cubrirá disponibilidad, duplicados, ceros iniciales,
correcciones, cuarentena, lotería desconocida, clasificación y límites de
reintento, concurrencia con recuperación de duplicate-key y conflicto de dos
identidades, sanitización recursiva de `raw_payload`, cuarentenas, `sync_errors`,
logs y fallo final de job, comandos, dry-run, capacidades y fake sin internet.
Las pruebas existentes de Fase 0 y 1A se preservan. También se
ejecutarán lint, typecheck, pruebas/build web, Compose local, E2E y build de
producción.

No se crean migraciones nuevas salvo que una prueba descubra que las tablas de
Fase 1A no pueden almacenar los datos ya definidos; el diseño parte de sus
campos existentes.
