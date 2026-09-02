# Fase 1A: dominio y lectura de loterías y sorteos

## Alcance

Esta entrega incorpora exclusivamente la base de dominio, persistencia y API de
lectura autenticada para loterías, horarios, sorteos y trazabilidad de futuras
sincronizaciones. No realiza llamadas a proveedores, no agenda procesos, no
ejecuta jobs y no añade interfaz de sorteos ni lógica de métodos, señales,
pagos, capital, backtesting o palé.

El documento adjunto de Fase 1 se usa como referencia funcional. Cuando difiere
del pedido aprobado, prevalece el alcance explícito de Fase 1A descrito aquí.

## Enfoques considerados

1. **Capas estrictas con un repositorio por agregado.** Aísla Eloquent mediante
   interfaces y adaptadores completos, pero introduce abstracciones de escritura
   que esta fase todavía no consume.
2. **Capas pragmáticas con consultas de aplicación (seleccionado).** Mantiene
   tipos puros en `Domain`, DTO y casos de consulta en `Application`, modelos
   Eloquent en `Infrastructure` y transporte en `Http`. Los casos de consulta
   encapsulan los builders de lectura sin anticipar puertos de escritura.
3. **Laravel convencional desde controllers.** Es más corto, pero acopla HTTP a
   persistencia y contradice la separación `Domain/Application/Infrastructure/Http`.

## Organización del código

- `app/Domain/Draws/Enums`: `DrawStatus`, `SyncRunStatus`, `SyncTrigger` y
  `SyncErrorType`.
- `app/Domain/Draws/ValueObjects/LotteryNumber.php`: representación inmutable de
  un número de lotería.
- `app/Application/Draws/Data/NormalizedDrawData.php`: DTO normalizado sin tipos,
  clientes ni payloads propios de un proveedor HTTP.
- `app/Application/Draws/Queries`: casos de uso de lectura para colecciones y
  detalles de loterías, sorteos y ejecuciones de sincronización.
- `app/Infrastructure/Persistence/Eloquent/Models`: modelos y relaciones Eloquent.
- `app/Infrastructure/Persistence/Eloquent/Casts`: cast de los tres premios a
  `LotteryNumber` cuando corresponda.
- `app/Http/Controllers/Api/V1`, `app/Http/Requests` y `app/Http/Resources`:
  transporte REST versionado, validación y serialización.

`User` permanece donde lo dejó la Fase 0; moverlo no forma parte de esta entrega.

## Modelo de persistencia

Se crearán siete migraciones independientes y reversibles, en orden de
dependencia. Todos los identificadores internos serán `BIGINT UNSIGNED` y todos
los timestamps de Laravel usarán la precisión predeterminada del proyecto.

### `lotteries`

| columna | definición |
| --- | --- |
| `id` | PK autoincremental |
| `external_id` | `SMALLINT UNSIGNED`, único |
| `name` | `VARCHAR(120)` |
| `slug` | `VARCHAR(120)`, único |
| `timezone` | `VARCHAR(64)`, default `America/Santo_Domingo` |
| `is_active` | boolean, default `true` |
| `sort_order` | `SMALLINT UNSIGNED`, default `0` |
| `created_at`, `updated_at` | timestamps |

### `lottery_schedules`

| columna | definición |
| --- | --- |
| `id` | PK autoincremental |
| `lottery_id` | FK requerida a `lotteries`, `RESTRICT` al borrar |
| `weekday` | `TINYINT UNSIGNED`, check `1 <= weekday <= 7` |
| `draw_time_local` | `TIME` |
| `sales_close_time_local` | `TIME`, nullable |
| `effective_from` | `DATE` |
| `effective_to` | `DATE`, nullable, check no anterior a `effective_from` |
| `is_active` | boolean, default `true` |
| `created_at`, `updated_at` | timestamps |

La clave única será `(lottery_id, weekday, draw_time_local, effective_from)`, de
modo que el esquema no prohíba dos sorteos confirmados para el mismo día. La
prevención de vigencias solapadas se aplicará en el futuro caso de escritura: en
Fase 1A no existe dicho caso ni se siembran horarios. Las vigencias serán
inclusivas. El cierre se interpreta en la zona horaria de la lotería: si su hora
es posterior a `draw_time_local`, pertenece al día calendario anterior; en otro
caso pertenece al mismo día.

### `sync_runs`

| columna | definición |
| --- | --- |
| `id` | PK autoincremental |
| `uuid` | `CHAR(36)`, único |
| `provider` | `VARCHAR(64)` |
| `trigger` | `VARCHAR(32)` con check del enum |
| `lottery_id` | FK nullable a `lotteries`, `RESTRICT` al borrar |
| `requested_from`, `requested_to` | `DATE`, nullable, check de rango cuando existan ambas |
| `status` | `VARCHAR(32)` con check del enum |
| `items_received`, `items_inserted`, `items_updated`, `items_unchanged`, `items_quarantined` | `INT UNSIGNED`, default `0` |
| `http_status` | `SMALLINT UNSIGNED`, nullable, check `100-599` |
| `started_at`, `finished_at` | `DATETIME(6)`; `finished_at` nullable |
| `duration_ms` | `BIGINT UNSIGNED`, nullable |
| `metadata` | JSON, nullable |
| `created_at`, `updated_at` | timestamps |

### `draws`

| columna | definición |
| --- | --- |
| `id` | PK autoincremental |
| `lottery_id` | FK requerida a `lotteries`, `RESTRICT` al borrar |
| `provider` | `VARCHAR(64)` |
| `external_draw_id` | `VARCHAR(191)`, nullable |
| `draw_date_local` | `DATE` |
| `scheduled_at_utc`, `drawn_at_utc` | `DATETIME(6)`, nullable |
| `p1`, `p2`, `p3` | `CHAR(2)`, check binario equivalente a `^[0-9]{2}$` |
| `status` | `VARCHAR(32)` con check del enum |
| `source_hash` | `CHAR(64)` |
| `raw_payload` | JSON |
| `received_at` | `DATETIME(6)` |
| `confirmed_at`, `corrected_at` | `DATETIME(6)`, nullable |
| `created_at`, `updated_at` | timestamps |

Tendrá claves únicas `(lottery_id, provider, external_draw_id)` y
`(lottery_id, scheduled_at_utc)`, además de índices
`(lottery_id, draw_date_local)` y `(status, draw_date_local)`. Se exigirá que
exista `external_draw_id` o `scheduled_at_utc`; una entrada sin ninguna identidad
confiable pertenece a cuarentena, no a `draws`. MySQL permite múltiples valores
`NULL` en índices únicos.

### `draw_corrections`

| columna | definición |
| --- | --- |
| `id` | PK autoincremental |
| `draw_id` | FK requerida a `draws`, `RESTRICT` al borrar |
| `sync_run_id` | FK nullable a `sync_runs`, `RESTRICT` al borrar |
| `before_payload`, `after_payload` | JSON |
| `before_hash`, `after_hash` | `CHAR(64)` |
| `detected_at` | `DATETIME(6)` |
| `created_at` | timestamp; no se actualizará esta auditoría append-only |

### `sync_errors`

| columna | definición |
| --- | --- |
| `id` | PK autoincremental |
| `sync_run_id` | FK requerida a `sync_runs`, `RESTRICT` al borrar |
| `lottery_id` | FK nullable a `lotteries`, `RESTRICT` al borrar |
| `type` | `VARCHAR(32)` con check del enum |
| `message` | `TEXT` |
| `http_status` | `SMALLINT UNSIGNED`, nullable, check `100-599` |
| `retryable` | boolean, default `false` |
| `attempt` | `SMALLINT UNSIGNED`, default `1`, check mayor que cero |
| `safe_context` | JSON, nullable |
| `occurred_at`, `resolved_at` | `DATETIME(6)`; resolución nullable |
| `created_at` | timestamp |

### `draw_quarantines`

| columna | definición |
| --- | --- |
| `id` | PK autoincremental |
| `sync_run_id` | FK requerida a `sync_runs`, `RESTRICT` al borrar |
| `lottery_id` | FK nullable a `lotteries`, `RESTRICT` al borrar |
| `raw_payload` | JSON |
| `error_code` | `VARCHAR(64)` |
| `validation_errors` | JSON |
| `resolved_at` | `DATETIME(6)`, nullable |
| `resolved_by` | FK nullable a `users`, `NULL` al borrar usuario |
| `created_at`, `updated_at` | timestamps |

Los registros históricos no desaparecerán en cascada. MySQL impondrá checks de
rango, formato e identidad además de la validación de aplicación. Los premios se
almacenarán como cadenas de dos dígitos, nunca como números ni `float`.

Para `draws` se conservarán las dos identidades:

- `external_id` en el filtro HTTP identifica `lotteries.external_id`.
- `external_draw_id` identifica el sorteo dentro del proveedor.

La identidad preferida será la externa. El respaldo por fecha y hora cumple la
regla de dominio sin impedir más de un sorteo diario de una lotería.

## Tipos de dominio

### `LotteryNumber`

Acepta valores `int|string`; el constructor recibe `mixed` únicamente para poder
rechazar de modo uniforme todo otro tipo con `InvalidArgumentException`. Un
entero entre `0` y `99` se normaliza con dos dígitos; una cadena debe contener
solo uno o dos dígitos y también se normaliza. Devuelve siempre `00`–`99`,
conserva el significado de ceros iniciales y rechaza negativos, valores mayores
de `99`, letras, cadenas vacías, `null`, booleanos y `float`. La comparación y
serialización usan su valor canónico.

### Enums

- `DrawStatus`: `pending`, `confirmed`, `corrected`, `invalid`.
- `SyncRunStatus`: `queued`, `running`, `succeeded`, `partial`, `failed`.
- `SyncTrigger`: `manual`, `scheduled`, `reconciliation`, `historical`.
- `SyncErrorType`: `network`, `authentication`, `rate_limit`, `validation`,
  `persistence`, `unknown`.

Los enums persistirán como `VARCHAR` con checks de MySQL y casts Eloquent. Así se
mantienen códigos estables sin usar enums nativos difíciles de evolucionar.

### `NormalizedDrawData`

Será un DTO inmutable con campos explícitos: `lotteryExternalId: int`,
`provider: string`, `externalDrawId: ?string`,
`drawDateLocal: DateTimeImmutable`, `scheduledAtUtc: ?DateTimeImmutable`,
`drawnAtUtc: ?DateTimeImmutable`, `p1/p2/p3: LotteryNumber`,
`status: DrawStatus`, `sourceHash: string`, `rawPayload: array` y
`receivedAt: DateTimeImmutable`. No aceptará respuestas HTTP ni conocerá nombres
de campos remotos. Se incluye aunque su consumidor llegue en Fase 1B porque el
usuario lo exige expresamente; Fase 1A probará únicamente su contrato puro.

## Seeder y factories

`LotterySeeder` usará `updateOrCreate` por `external_id` para cargar estas diez
filas con valores deterministas:

| external_id | nombre | slug | sort_order |
| ---: | --- | --- | ---: |
| 4 | Lotería Nacional | `loteria-nacional` | 10 |
| 5 | Quiniela Leidsa | `quiniela-leidsa` | 20 |
| 6 | Quiniela Loteka | `quiniela-loteka` | 30 |
| 12 | Gana Más | `gana-mas` | 40 |
| 13 | Quiniela Real | `quiniela-real` | 50 |
| 15 | Quiniela LoteDom | `quiniela-lotedom` | 60 |
| 18 | La Primera Noche | `la-primera-noche` | 70 |
| 20 | La Primera Tarde | `la-primera-tarde` | 80 |
| 21 | La Suerte MD | `la-suerte-md` | 90 |
| 29 | La Suerte 6 PM | `la-suerte-6-pm` | 100 |

Todas usarán `America/Santo_Domingo` e `is_active=true`. “Exactamente” describe
el catálogo insertado en una base vacía; una repetición actualizará esas filas
sin duplicarlas ni borrar loterías ajenas.

No se sembrarán horarios. `LotteryScheduleFactory` permitirá producirlos en
pruebas y la documentación de entrega registrará como pendiente la fuente
oficial de días, horas, cierres y vigencias. Todas las entidades tendrán factory
con estados coherentes para relaciones y estados principales.

El seeder global no volverá a crear un usuario con contraseña conocida: invocará
solo el catálogo seguro de loterías, preservando el flujo efímero
`app:create-owner` de Fase 0.

## API autenticada

Las rutas estarán bajo `/api/v1` y `auth:sanctum`:

- `GET /lotteries`
- `GET /lotteries/{lottery}`
- `GET /draws`
- `GET /draws/{draw}`
- `GET /sync-runs`
- `GET /sync-runs/{syncRun}`

Los endpoints de colección devolverán API Resources paginados. Los detalles
cargarán relaciones definidas sin provocar N+1. El route model binding de
`{lottery}`, `{draw}` y `{syncRun}` usará la PK interna numérica. Al no existir
roles adicionales en Fase 0, estar autenticado por Sanctum constituye la
autorización temporal disponible en esta fase; crear roles o ownership se
difiere hasta que el producto los defina. Las rutas públicas responderán `401`.

`DrawIndexRequest` admitirá `lottery_id`, `external_id`, `from`, `to`, `status`,
`page` y `per_page`. Validará existencia, enteros positivos, formato
`YYYY-MM-DD`, `to >= from`, enum y límites. `per_page` tendrá default `25` y
máximo duro de `100`. `from` y `to` serán inclusivos sobre `draw_date_local`.
`external_id` filtrará por la relación con la lotería. Si se envían ambas
identidades, se aplicarán con `AND`; una combinación que no corresponda devolverá
una colección vacía, no un error.

Las colecciones tendrán orden estable:

- loterías: `sort_order ASC, id ASC`;
- sorteos: `draw_date_local DESC, scheduled_at_utc DESC, id DESC`;
- ejecuciones: `started_at DESC, id DESC`.

La paginación usará el sobre estándar de Laravel con `data`, `links` y `meta`.
Las respuestas de detalle usarán `data`; los errores conservarán el JSON estándar
de Laravel para `401`, `404` y `422`.

### Campos de Resources

- `LotteryResource` de colección: `id`, `external_id`, `name`, `slug`,
  `timezone`, `is_active`, `sort_order`.
- Detalle de lotería: los campos anteriores y `schedules`, cada uno con `id`,
  `weekday`, `draw_time_local`, `sales_close_time_local`, `effective_from`,
  `effective_to` e `is_active`.
- `DrawResource` de colección: `id`, resumen de `lottery` (`id`, `external_id`,
  `name`), `provider`, `external_draw_id`, `draw_date_local`,
  `scheduled_at_utc`, `drawn_at_utc`, `p1`, `p2`, `p3`, `status`, `source_hash`,
  `received_at`, `confirmed_at` y `corrected_at`.
- Detalle de sorteo: los campos anteriores más `raw_payload`.
- `SyncRunResource` de colección y detalle: `id`, `uuid`, `provider`, `trigger`,
  `lottery_id`, `requested_from`, `requested_to`, `status`, los cinco contadores,
  `http_status`, `started_at`, `finished_at` y `duration_ms`. No se expondrán
  `metadata`, `safe_context` ni payloads de cuarentena en Fase 1A.

Los recursos de colección de sorteos nunca incluirán `raw_payload`. El recurso
de detalle sí lo incluirá tras pasar `auth:sanctum`. Los recursos expondrán
números como strings canónicos y JSON como objetos/arreglos, no como texto JSON.

Aunque todavía no existen casos de escritura, ningún payload, metadata o contexto
podrá guardar tokens, cookies ni cabeceras de autorización. Esta regla quedará
cubierta por documentación y será responsabilidad del normalizador de Fase 1B;
Fase 1A no fingirá una sanitización sin entrada externa.

## Pruebas

La suite Pest se ampliará con pruebas unitarias y feature sobre MySQL 8.4 para:

- creación de las siete tablas, claves foráneas, índices, unicidad y checks;
- inspección de checks, índices y acciones referenciales mediante
  `information_schema` de MySQL 8.4;
- rollback ordenado de las migraciones en una base efímera aislada;
- relaciones y casts JSON/enum/value-object;
- idempotencia y contenido exacto del seeder;
- autenticación Sanctum en los seis endpoints;
- filtros, intervalo de fechas, paginación y límite de `per_page`;
- ausencia de `raw_payload` en listados y presencia solo en detalle autenticado;
- serialización exacta de `00`, `01` y `09`;
- aceptación y rechazo exhaustivos de `LotteryNumber`.

Las pruebas existentes de auth, health y creación efímera del propietario se
mantendrán como protección de regresión de Fase 0. No se sustituirá MySQL por
SQLite.

## Compatibilidad y verificación

No se añadirán dependencias. Las migraciones y variables conservarán los
contratos de Docker local y producción. La validación final ejecutará todas las
puertas backend, frontend, Docker, E2E y build de producción indicadas por el
pedido. La entrega solo se publicará cuando el PR tenga sus tres jobs verdes.

## Fuera de alcance confirmado

No se implementarán adaptadores HTTP de proveedores, normalizadores específicos,
comandos de sincronización, scheduler, jobs, Horizon para ingesta, UI React de
sorteos, métodos, señales, pagos, capital, backtesting ni palé. Esos cambios
corresponden a Fase 1B o posteriores.

Tampoco habrá endpoints de escritura o corrección. El futuro caso de corrección
deberá ser transaccional, append-only y registrar `draw_corrections` antes de
alterar un sorteo confirmado; ese comportamiento no se implementará aquí.
