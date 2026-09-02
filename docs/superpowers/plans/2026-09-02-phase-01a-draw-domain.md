# Fase 1A: dominio y API de lectura de sorteos Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Incorporar el catálogo de loterías, persistencia auditable de sorteos y sincronizaciones, y los seis endpoints autenticados de lectura sin iniciar la Fase 1B.

**Architecture:** Tipos y reglas puros vivirán en `Domain`; el DTO y las consultas de lectura en `Application`; los modelos, casts y migraciones Eloquent en `Infrastructure`; y controllers, requests y Resources en `Http`. Los endpoints consumirán casos de consulta, paginarán de forma estable y solo expondrán `draws.raw_payload` en el detalle autenticado.

**Tech Stack:** PHP 8.4, Laravel 13, MySQL 8.4, Sanctum, Pest 4, PHPStan/Larastan, Pint, Docker Compose, React/Vite/Playwright para regresión de Fase 0.

---

## Estructura de archivos

| Archivo | Responsabilidad |
| --- | --- |
| `apps/api/app/Domain/Draws/Enums/*.php` | Valores persistibles tipados de estado y trazabilidad. |
| `apps/api/app/Domain/Draws/ValueObjects/LotteryNumber.php` | Normalización estricta e inmutable de `00`–`99`. |
| `apps/api/app/Application/Draws/Data/NormalizedDrawData.php` | Contrato de dato normalizado, sin HTTP de proveedor. |
| `apps/api/app/Infrastructure/Persistence/Eloquent/{Models,Casts}/*.php` | Modelos, relaciones y cast de premio. |
| `apps/api/database/migrations/2026_09_02_12000*_create_*_table.php` | Esquema MySQL reversible y con restricciones. |
| `apps/api/database/factories/*.php` | Datos coherentes y relaciones para pruebas. |
| `apps/api/database/seeders/LotterySeeder.php` | Catálogo idempotente de diez loterías. |
| `apps/api/app/Application/Draws/Queries/*.php` | Consultas de colección y detalle. |
| `apps/api/app/Http/{Controllers/Api/V1,Requests/Draws,Resources}/*.php` | Validación, transporte y serialización API. |
| `apps/api/routes/api.php` | Rutas `/api/v1` protegidas por Sanctum. |
| `apps/api/tests/{Unit,Feature}/Draws/*.php` | Pruebas de dominio, esquema, seeder y API. |
| `docs/phases/PHASE_01A_DRAW_DOMAIN.md` | Límites, catálogo y datos oficiales pendientes de horarios. |

### Task 1: Preparar la base de pruebas MySQL y las pruebas de dominio

**Files:**
- Create: `apps/api/tests/Unit/Draws/LotteryNumberTest.php`
- Create: `apps/api/tests/Unit/Draws/NormalizedDrawDataTest.php`
- Create: `apps/api/tests/Feature/Draws/MigrationSchemaTest.php`
- Modify: `apps/api/tests/Pest.php`

- [ ] **Step 1: Levantar dependencias de prueba MySQL y Redis**

Run: `docker-compose -f docker-compose.dependencies.yml up -d --wait`

Expected: MySQL 8.4 en `127.0.0.1:3307`, Redis en `127.0.0.1:6380` y la base
`quinielalab_test` saludables. No continuar si Compose informa error; las
pruebas de Laravel usan exactamente esos valores de `phpunit.xml`.

- [ ] **Step 2: Escribir las pruebas fallidas de `LotteryNumber`**

```php
it('normalizes lottery numbers without losing leading zeroes', function (int|string $input, string $expected): void {
    expect((string) new LotteryNumber($input))->toBe($expected);
})->with([[0, '00'], ['01', '01'], [9, '09'], ['09', '09'], [99, '99']]);

it('rejects invalid lottery numbers', function (mixed $input): void {
    expect(fn () => new LotteryNumber($input))->toThrow(InvalidArgumentException::class);
})->with([-1, 100, '100', 'ab', '', null, 1.2, true]);
```

- [ ] **Step 3: Ejecutar la prueba para confirmar el fallo**

Run: `cd apps/api && /opt/homebrew/opt/php/bin/php artisan test tests/Unit/Draws/LotteryNumberTest.php`

Expected: FAIL porque `LotteryNumber` todavía no existe.

- [ ] **Step 4: Crear el esqueleto de pruebas de DTO y esquema MySQL**

Crear el test de DTO con `DateTimeImmutable`, `LotteryNumber` y `DrawStatus`, comprobando que no contiene tipos HTTP. En `MigrationSchemaTest`, usar la conexión MySQL de prueba y `information_schema` para confirmar que las siete tablas aún no existen antes de las migraciones nuevas; no usar SQLite ni assertions dependientes de SQLite.

- [ ] **Step 5: Aislar pruebas de integración de las pruebas unitarias**

Mantener `RefreshDatabase` para Feature. Añadir al grupo de integración solamente las pruebas que consultan `information_schema`, para no esconder fallos de esquema bajo el comportamiento de SQLite.

- [ ] **Step 6: Ejecutar las pruebas de arranque**

Run: `cd apps/api && /opt/homebrew/opt/php/bin/php artisan test tests/Unit/Draws tests/Feature/Draws/MigrationSchemaTest.php`

Expected: FAIL solo por los símbolos y tablas aún ausentes.

- [ ] **Step 7: Commit**

```bash
git add apps/api/tests/Pest.php apps/api/tests/Unit/Draws apps/api/tests/Feature/Draws/MigrationSchemaTest.php
git commit -m "test: define draw domain expectations"
```

### Task 2: Implementar los tipos puros del dominio

**Files:**
- Create: `apps/api/app/Domain/Draws/Enums/DrawStatus.php`
- Create: `apps/api/app/Domain/Draws/Enums/SyncRunStatus.php`
- Create: `apps/api/app/Domain/Draws/Enums/SyncTrigger.php`
- Create: `apps/api/app/Domain/Draws/Enums/SyncErrorType.php`
- Create: `apps/api/app/Domain/Draws/ValueObjects/LotteryNumber.php`
- Create: `apps/api/app/Application/Draws/Data/NormalizedDrawData.php`
- Test: `apps/api/tests/Unit/Draws/LotteryNumberTest.php`
- Test: `apps/api/tests/Unit/Draws/NormalizedDrawDataTest.php`

- [ ] **Step 1: Implementar los enums backed por string**

Declarar exactamente:

```php
enum DrawStatus: string { case Pending = 'pending'; case Confirmed = 'confirmed'; case Corrected = 'corrected'; case Invalid = 'invalid'; }
enum SyncRunStatus: string { case Queued = 'queued'; case Running = 'running'; case Succeeded = 'succeeded'; case Partial = 'partial'; case Failed = 'failed'; }
enum SyncTrigger: string { case Manual = 'manual'; case Scheduled = 'scheduled'; case Reconciliation = 'reconciliation'; case Historical = 'historical'; }
enum SyncErrorType: string { case Network = 'network'; case Authentication = 'authentication'; case RateLimit = 'rate_limit'; case Validation = 'validation'; case Persistence = 'persistence'; case Unknown = 'unknown'; }
```

- [ ] **Step 2: Implementar `LotteryNumber` sin coerciones de float**

Exponer constructor `__construct(mixed $value)` para rechazar uniformemente tipos
no permitidos con `InvalidArgumentException`; el contrato aceptado seguirá siendo
solo `int|string`. Validar la cadena con `/^[0-9]{1,2}$/D`, validar el rango
entero y guardar el valor con `str_pad(..., 2, '0', STR_PAD_LEFT)`. Implementar
`__toString()` y un método `value(): string`; no castear `float` ni perder ceros.

- [ ] **Step 3: Implementar el DTO inmutable**

Usar `readonly class NormalizedDrawData` y exactamente estas propiedades:

```php
public int $lotteryExternalId;
public string $provider;
public ?string $externalDrawId;
public DateTimeImmutable $drawDateLocal;
public ?DateTimeImmutable $scheduledAtUtc;
public ?DateTimeImmutable $drawnAtUtc;
public LotteryNumber $p1;
public LotteryNumber $p2;
public LotteryNumber $p3;
public DrawStatus $status;
public string $sourceHash;
/** @var array<string, mixed> */ public array $rawPayload;
public DateTimeImmutable $receivedAt;
```

No importar clientes HTTP, Request, Response ni formatos de proveedor.

- [ ] **Step 4: Ejecutar unitarias y análisis estático**

Run: `cd apps/api && /opt/homebrew/opt/php/bin/php artisan test tests/Unit/Draws && vendor/bin/phpstan analyse --memory-limit=512M`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Domain/Draws apps/api/app/Application/Draws/Data apps/api/tests/Unit/Draws
git commit -m "feat: add typed draw domain primitives"
```

### Task 3: Crear migraciones MySQL verificables

**Files:**
- Create: `apps/api/database/migrations/2026_09_02_120000_create_lotteries_table.php`
- Create: `apps/api/database/migrations/2026_09_02_120100_create_lottery_schedules_table.php`
- Create: `apps/api/database/migrations/2026_09_02_120200_create_sync_runs_table.php`
- Create: `apps/api/database/migrations/2026_09_02_120300_create_draws_table.php`
- Create: `apps/api/database/migrations/2026_09_02_120400_create_draw_corrections_table.php`
- Create: `apps/api/database/migrations/2026_09_02_120500_create_sync_errors_table.php`
- Create: `apps/api/database/migrations/2026_09_02_120600_create_draw_quarantines_table.php`
- Modify: `apps/api/tests/Feature/Draws/MigrationSchemaTest.php`

- [ ] **Step 1: Escribir assertions precisas de esquema y rollback**

Comprobar mediante `information_schema.tables`, `statistics`, `table_constraints`,
`key_column_usage` y `check_constraints` las tablas, FKs `RESTRICT`/`SET NULL`,
índices únicos e índices de `draws`. Ejecutar el rollback de las siete
migraciones en una base aislada y confirmar que sus tablas desaparecen; volver a
migrar para no afectar las demás pruebas.

- [ ] **Step 2: Ejecutar la prueba para confirmar el fallo**

Run: `cd apps/api && /opt/homebrew/opt/php/bin/php artisan test tests/Feature/Draws/MigrationSchemaTest.php`

Expected: FAIL porque no existen las tablas y restricciones.

- [ ] **Step 3: Crear las migraciones de catálogo, horario y ejecución**

Aplicar las columnas, longitudes, defaults, checks y FKs de la especificación.
Usar `dateTime(..., 6)` para instantes UTC y `time`/`date` para tiempo local.
Usar `string` más checks, no enums MySQL nativos. Añadir checks por
`DB::statement` con nombres estables para `weekday`, vigencia, rangos solicitados,
estado HTTP, contadores y valores de enum.

- [ ] **Step 4: Crear las migraciones de sorteos y auditoría**

Aplicar las dos identidades de `draws`:

```sql
UNIQUE (lottery_id, provider, external_draw_id)
UNIQUE (lottery_id, scheduled_at_utc)
CHECK (external_draw_id IS NOT NULL OR scheduled_at_utc IS NOT NULL)
```

Agregar checks de los premios con una condición binaria equivalente a dos dígitos,
hashes `CHAR(64)`, JSON y las relaciones de correcciones, errores y cuarentenas.
`resolved_by` debe ser nullable y usar `nullOnDelete`; no usar cascadas para
histórico.

- [ ] **Step 5: Ejecutar las migraciones y la prueba de esquema**

Run: `cd apps/api && /opt/homebrew/opt/php/bin/php artisan migrate:fresh --force && /opt/homebrew/opt/php/bin/php artisan test tests/Feature/Draws/MigrationSchemaTest.php`

Expected: PASS en MySQL 8.4, incluido rollback y remigración.

- [ ] **Step 6: Commit**

```bash
git add apps/api/database/migrations apps/api/tests/Feature/Draws/MigrationSchemaTest.php
git commit -m "feat: add auditable draw persistence schema"
```

### Task 4: Modelos Eloquent, cast y factories

**Files:**
- Create: `apps/api/app/Infrastructure/Persistence/Eloquent/Casts/LotteryNumberCast.php`
- Create: `apps/api/app/Infrastructure/Persistence/Eloquent/Models/{Lottery,LotterySchedule,Draw,DrawCorrection,SyncRun,SyncError,DrawQuarantine}.php`
- Create: `apps/api/database/factories/{Lottery,LotterySchedule,Draw,DrawCorrection,SyncRun,SyncError,DrawQuarantine}Factory.php`
- Create: `apps/api/tests/Feature/Draws/ModelRelationsTest.php`
- Modify: `apps/api/tests/Feature/Draws/MigrationSchemaTest.php`

- [ ] **Step 1: Escribir pruebas fallidas de relaciones y casts**

Crear un sorteo con `p1='00'`, `p2='01'`, `p3='09'`, JSON de payload y metadata.
Comprobar que el modelo devuelve tres `LotteryNumber`, que los JSON son arrays y
que las relaciones incluyen `Lottery->schedules/draws/syncRuns`,
`Draw->lottery/corrections`, `SyncRun->lottery/errors/quarantines`,
`DrawCorrection->draw/syncRun`, `SyncError->syncRun/lottery` y
`DrawQuarantine->syncRun/lottery/resolvedBy`.

- [ ] **Step 2: Ejecutar la prueba para confirmar el fallo**

Run: `cd apps/api && /opt/homebrew/opt/php/bin/php artisan test tests/Feature/Draws/ModelRelationsTest.php`

Expected: FAIL por modelos, factories y cast inexistentes.

- [ ] **Step 3: Implementar modelos y relaciones explícitas**

Usar `HasFactory`, `fillable` limitado y `casts()` para enums, JSON, booleanos y
fechas. `Draw` usará `LotteryNumberCast::class` en `p1`, `p2`, `p3`; el cast debe
escribir solo strings canónicos. Declarar tipos de retorno de todas las relaciones
Eloquent y no incluir lógica de sincronización. En `DrawCorrection` y `SyncError`
declarar `public const UPDATED_AT = null;`, pues las migraciones de auditoría solo
tienen `created_at`; comprobar sus casts enum en la prueba de relaciones.

- [ ] **Step 4: Implementar factories coherentes**

Cada factory debe generar los campos requeridos y relacionarse mediante
`for(Lottery::factory())` o `for(SyncRun::factory())`. `DrawFactory` debe crear
una identidad externa o un `scheduled_at_utc` y números válidos de dos dígitos,
con estados `pending`, `confirmed`, `corrected` e `invalid`. `SyncRunFactory`
debe ofrecer estados `queued`, `running`, `succeeded`, `partial` y `failed`.
`LotteryScheduleFactory` será solo para tests y no alimentará el seeder; no habrá
caso de escritura que valide solapamientos en Fase 1A.

- [ ] **Step 5: Ejecutar pruebas de persistencia**

Run: `cd apps/api && /opt/homebrew/opt/php/bin/php artisan test tests/Feature/Draws/ModelRelationsTest.php tests/Feature/Draws/MigrationSchemaTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add apps/api/app/Infrastructure/Persistence/Eloquent apps/api/database/factories apps/api/tests/Feature/Draws/ModelRelationsTest.php
git commit -m "feat: add draw persistence models and factories"
```

### Task 5: Seeder seguro y documentación de horarios pendientes

**Files:**
- Create: `apps/api/database/seeders/LotterySeeder.php`
- Modify: `apps/api/database/seeders/DatabaseSeeder.php`
- Create: `apps/api/tests/Feature/Draws/LotterySeederTest.php`
- Create: `docs/phases/PHASE_01A_DRAW_DOMAIN.md`

- [ ] **Step 1: Escribir pruebas fallidas del catálogo**

En una base vacía, ejecutar `LotterySeeder` y afirmar los diez `external_id`,
nombres, slugs, timezone, actividad y orden exactos. Ejecutarlo dos veces y
comprobar que la cantidad sigue siendo diez. Crear una lotería ajena y comprobar
que una nueva ejecución del seeder no la elimina.

- [ ] **Step 2: Ejecutar la prueba para confirmar el fallo**

Run: `cd apps/api && /opt/homebrew/opt/php/bin/php artisan test tests/Feature/Draws/LotterySeederTest.php`

Expected: FAIL porque no existe `LotterySeeder`.

- [ ] **Step 3: Implementar el seeder idempotente**

Usar `Lottery::query()->updateOrCreate(['external_id' => $externalId], [...])`
para las diez filas definidas. Sustituir el usuario fijo de `DatabaseSeeder` por
`$this->call(LotterySeeder::class)`; no sembrar usuarios ni contraseñas conocidas.

- [ ] **Step 4: Documentar la información no confirmada**

En `docs/phases/PHASE_01A_DRAW_DOMAIN.md`, registrar que antes de sembrar
`lottery_schedules` se requiere, para cada lotería, fuente oficial de weekday,
hora local, hora de cierre y rango de vigencia. Declarar que ningún horario se
infiere de su nombre. Documentar además que `raw_payload`, `metadata` y
`safe_context` nunca podrán persistir tokens, cookies ni cabeceras de
autorización; la sanitización de una respuesta de proveedor será responsabilidad
del normalizador de Fase 1B.

- [ ] **Step 5: Ejecutar pruebas de seeder y regresión del propietario**

Run: `cd apps/api && /opt/homebrew/opt/php/bin/php artisan test tests/Feature/Draws/LotterySeederTest.php tests/Feature/CreateOwnerCommandTest.php`

Expected: PASS; el propietario solo se crea mediante el comando efímero de Fase 0.

- [ ] **Step 6: Commit**

```bash
git add apps/api/database/seeders apps/api/tests/Feature/Draws/LotterySeederTest.php docs/phases/PHASE_01A_DRAW_DOMAIN.md
git commit -m "feat: seed the initial lottery catalog"
```

### Task 6: Casos de consulta, requests, Resources, controllers y rutas

**Files:**
- Create: `apps/api/app/Application/Draws/Queries/{ListLotteries,GetLottery,ListDraws,GetDraw,ListSyncRuns,GetSyncRun}.php`
- Create: `apps/api/app/Http/Controllers/Api/V1/{LotteryController,DrawController,SyncRunController}.php`
- Create: `apps/api/app/Http/Requests/Draws/DrawIndexRequest.php`
- Create: `apps/api/app/Http/Resources/{LotteryResource,LotteryScheduleResource,DrawResource,SyncRunResource}.php`
- Modify: `apps/api/routes/api.php`
- Create: `apps/api/tests/Feature/Draws/ReadApiTest.php`

- [ ] **Step 1: Escribir las pruebas de API fallidas**

Cubrir `401` para los seis endpoints sin Sanctum y `200` con
`Sanctum::actingAs(User::factory()->create())`. Crear dos loterías, sorteos de
fechas/estados distintos y dos ejecuciones; verificar el sobre `data/links/meta`,
orden, binding por PK, `404` y las formas exactas de Resource.

- [ ] **Step 2: Cubrir filtros, validación y exposición de payload**

Probar individualmente `lottery_id`, `external_id`, `from`, `to`, `status`,
`page` y `per_page`; probar IDs inexistentes, IDs y páginas no positivos,
`from > to`, fechas con formato inválido, estados inválidos y `per_page=101` como
`422`; probar ambos IDs como intersección vacía. Afirmar que colección de draws
no incluye `raw_payload`, detalle autenticado sí, y que `00`, `01`, `09` son
strings exactos. Afirmar que `metadata` y `safe_context` no aparecen en Resources.

- [ ] **Step 3: Ejecutar la prueba para confirmar el fallo**

Run: `cd apps/api && /opt/homebrew/opt/php/bin/php artisan test tests/Feature/Draws/ReadApiTest.php`

Expected: FAIL por rutas y clases inexistentes.

- [ ] **Step 4: Implementar consultas de aplicación con orden estable**

Cada caso de uso recibirá valores ya validados y devolverá paginator/modelo. Las
consultas usarán eager loading y los órdenes definidos por la especificación:
`sort_order,id`; `draw_date_local,scheduled_at_utc,id`; y `started_at,id`.
`ListDraws` aplicará los filtros inclusivos y `whereHas('lottery')` para
`external_id`; no aceptará filtros de proveedor, jobs ni red externa.

- [ ] **Step 5: Implementar HTTP delgado y rutas**

Declarar `DrawIndexRequest::rules()` con `Rule::exists('lotteries', 'id')` para
`lottery_id`, `Rule::exists('lotteries', 'external_id')` para `external_id`,
`integer|min:1` para ambos IDs, `Rule::enum(DrawStatus::class)`,
`date_format:Y-m-d`, enteros `min:1` para página y `per_page`, y `max:100` para
este último. En `withValidator`, añadir error a `to` si es anterior a `from`.
El método `perPage()` devolverá `validated('per_page', 25)`. El controller
extraerá ese entero y lo pasará como argumento al caso de uso; este llamará
`paginate($perPage)` sin conocer `DrawIndexRequest` ni ninguna clase HTTP, nunca
el default de Laravel. Controllers delegan en los casos de consulta y devuelven
los Resources. Declarar nombres explícitos `lotteries.index`,
`lotteries.show`, `draws.index`, `draws.show`, `sync-runs.index` y
`sync-runs.show` dentro de `Route::prefix('v1')->middleware('auth:sanctum')`.
Usar binding por id numérico e inyectar el modelo solo como identificador de
detalle; no crear operaciones de escritura.

- [ ] **Step 6: Implementar Resources de lista y detalle**

`DrawResource` debe usar `when($request->routeIs('draws.show'), ...)` para
`raw_payload`. `LotteryResource` debe incluir horarios solo en detalle. Recursos
de sync run deben usar una allowlist explícita y no serializar `metadata`,
`safe_context` ni cuarentenas. Formatear instantes como ISO-8601 UTC y tiempos
locales/fechas como strings de base de datos.

- [ ] **Step 7: Ejecutar la prueba de API**

Run: `cd apps/api && /opt/homebrew/opt/php/bin/php artisan test tests/Feature/Draws/ReadApiTest.php`

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add apps/api/app/Application/Draws/Queries apps/api/app/Http apps/api/routes/api.php apps/api/tests/Feature/Draws/ReadApiTest.php
git commit -m "feat: expose authenticated draw read api"
```

### Task 7: Validación completa, contenedores y publicación del PR

**Files:**
- Modify: archivos de esta rama solo si una puerta de calidad revela un defecto dentro de Fase 1A.
- Do not modify: `apps/web/src/**` salvo una corrección requerida por regresión de build; no añadir UI de sorteos.

- [ ] **Step 1: Ejecutar calidad backend**

Run:

```bash
cd apps/api
composer validate --strict
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=512M
/opt/homebrew/opt/php/bin/php artisan test
```

Expected: todos PASS usando MySQL de prueba; corregir solo defectos de Fase 1A.

- [ ] **Step 2: Ejecutar regresiones frontend sin modificar su alcance**

Run:

```bash
cd apps/web
npm run lint
npm run typecheck
npm run test -- --run
npm run build
```

Expected: todos PASS.

- [ ] **Step 3: Validar el stack local e E2E existente**

Run:

```bash
cd ../..
docker compose config --quiet
docker compose build
```

Definir un bloque de shell con `trap` de limpieza antes de levantar el stack:

```bash
EXISTING_STACK="$(docker compose ps --status running -q)"
if [ -z "$EXISTING_STACK" ]; then
  E2E_PROJECT='phase01a-e2e'
  WEB_PORT=5174 docker compose -p "$E2E_PROJECT" up -d --wait
  E2E_BASE_URL='http://127.0.0.1:5174'
  cleanup() { docker compose -p "$E2E_PROJECT" down -v; }
else
  docker compose up -d --wait
  E2E_BASE_URL="http://127.0.0.1:$(docker compose port web 8080 | sed 's/.*://')"
  cleanup() { docker compose exec -T api php artisan tinker --execute="App\\Models\\User::query()->where('email', '${E2E_EMAIL}')->delete();"; }
fi
trap cleanup EXIT
E2E_EMAIL="e2e-$(uuidgen | tr '[:upper:]' '[:lower:]')@example.test"
E2E_PASSWORD="$(openssl rand -base64 32)"
if [ -n "${E2E_PROJECT:-}" ]; then
  printf '%s\n' "$E2E_PASSWORD" | docker compose -p "$E2E_PROJECT" exec -T api php artisan app:create-owner --email="$E2E_EMAIL" --name='E2E Owner' --password-stdin
else
  printf '%s\n' "$E2E_PASSWORD" | docker compose exec -T api php artisan app:create-owner --email="$E2E_EMAIL" --name='E2E Owner' --password-stdin
fi
cd apps/web
E2E_BASE_URL="$E2E_BASE_URL" E2E_EMAIL="$E2E_EMAIL" E2E_PASSWORD="$E2E_PASSWORD" npm run test:e2e
cd ../..
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' \
APP_URL='https://preditor.test' \
DB_HOST='mysql' DB_DATABASE='preditor' DB_USERNAME='preditor' DB_PASSWORD='preditor' \
REDIS_HOST='redis' REDIS_PASSWORD='preditor' SESSION_DOMAIN='preditor.test' \
SANCTUM_STATEFUL_DOMAINS='preditor.test' \
CORS_ALLOWED_ORIGINS='https://preditor.test' IMAGE_TAG='phase-01a-verify' \
docker compose -f docker-compose.prod.yml build
```

Expected: PASS; el E2E de Fase 0 seguirá realizando login real con Sanctum. No
imprimir ni exportar las credenciales efímeras a logs ni a archivos. Tras terminar
la verificación, el `trap` eliminará el stack y volúmenes aislados o solo el
propietario efímero si ya existía un stack local; nunca derribará volúmenes ajenos.

- [ ] **Step 4: Revisar alcance y estado Git**

Run: `git diff origin/main...HEAD --check && git status --short`

Expected: solo archivos de Fase 1A rastreados; conservar sin stage
`Plan_Mensual_Capital_RD20000_Quiniela_Pale.xlsx` y `docs/elboletoganador.sql.zip`.

- [ ] **Step 5: Commit final y publicar rama**

```bash
git add <solo-archivos-de-fase-1a>
git commit -m "test: verify phase 1a draw foundation"
git push -u origin codex/phase-01a-draw-domain
gh pr create --base main --head codex/phase-01a-draw-domain --title "feat: add lottery and draw domain foundation" --fill
```

Expected: PR abierto sin secretos ni cambios de Fase 1B.

- [ ] **Step 6: Verificar CI remoto**

Run: `gh pr checks --watch <PR_NUMBER>`

Expected: `backend`, `frontend` y `containers-and-e2e` verdes antes de declarar la entrega completa.
