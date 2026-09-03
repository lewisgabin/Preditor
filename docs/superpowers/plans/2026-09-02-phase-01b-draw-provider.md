# Fase 1B: proveedor idempotente de sorteos Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrar un proveedor configurable y seguro de sorteos actuales, con fake determinista, normalización, persistencia idempotente, correcciones auditables y comandos manuales, sin iniciar la Fase 1C.

**Architecture:** Los DTOs y el puerto viven en `Application`; las reglas de normalización usan los tipos puros ya presentes en `Domain`; HTTP, secretos, Redis, Eloquent y la cola viven en `Infrastructure`; los comandos Artisan son el borde de entrada. El caso de uso conserva un único `SyncRun` por ejecución lógica, persiste cada elemento transaccionalmente y emite eventos después del commit.

**Tech Stack:** PHP 8.4, Laravel 13, MySQL 8.4, Redis/Horizon, Laravel HTTP client, Pest 4, PHPStan/Larastan, Docker Compose y GitHub Actions.

---

## Estructura de archivos

| Archivo | Responsabilidad |
| --- | --- |
| `app/Application/Draws/Data/{DrawFetchRequest,DrawFetchResult,DrawProviderCapabilities}.php` | Solicitud, resultado y capacidades sin modelo Eloquent. |
| `app/Application/Draws/Contracts/LotteryDrawProvider.php` | Puerto interno independiente de HTTP. |
| `app/Application/Draws/{Normalization,Persistence,Services,Events}` | Normalizar, persistir, orquestar y avisar después de commit. |
| `app/Infrastructure/Draws/{Providers,Security,Jobs}` | Fake, adaptador HTTP, sanitización y job Laravel. |
| `app/Console/Commands/{SyncLotteryDraws,ReconcileLotteryDraws}.php` | Borde CLI, validación de opciones y resumen seguro. |
| `config/lottery-api.php` | Configuración tipada desde entorno, sin secreto por defecto. |
| `config/horizon.php` | Supervisor que escucha `draw-sync` además de `default`. |
| `.env.example`, Compose, Dokploy, README y documentación Fase 1 | Nombres de variables, activación y operación segura. |
| `tests/{Unit,Feature}/Draws/*` | Contratos, normalización, persistencia, job, CLI, seguridad y regresión. |

### Task 1: Definir contratos de Application y capacidades sin HTTP

**Files:**
- Create: `apps/api/app/Application/Draws/Data/DrawFetchRequest.php`
- Create: `apps/api/app/Application/Draws/Data/DrawFetchResult.php`
- Create: `apps/api/app/Application/Draws/Data/DrawProviderCapabilities.php`
- Create: `apps/api/app/Application/Draws/Contracts/LotteryDrawProvider.php`
- Test: `apps/api/tests/Unit/Draws/DrawFetchContractTest.php`

- [ ] Escribir pruebas que construyan solicitudes actuales, por fecha y por rango; rechazar fecha+rango, rango invertido y external ID no positivo.
- [ ] Ejecutar `cd apps/api && /opt/homebrew/opt/php/bin/php artisan test tests/Unit/Draws/DrawFetchContractTest.php` y confirmar fallo por símbolos ausentes.
- [ ] Crear DTOs `readonly`: la solicitud contiene provider, `lotteryExternalId`, trigger, fecha o rango; el resultado tiene estados explícitos `available`, `not_available`, `failure`; capacidades declaran current/date/range.
- [ ] Declarar `LotteryDrawProvider::capabilities(): DrawProviderCapabilities` y `fetch(DrawFetchRequest): DrawFetchResult`; ningún tipo recibe `Lottery` Eloquent, `Request`, `Response` ni URL.
- [ ] Ejecutar la prueba y `vendor/bin/phpstan analyse --memory-limit=512M`; confirmar PASS.
- [ ] Commit: `feat: add draw provider contracts`.

### Task 2: Configuración, selección de proveedor y sanitización central

**Files:**
- Create: `apps/api/config/lottery-api.php`
- Create: `apps/api/app/Infrastructure/Draws/Security/ProviderSecretSanitizer.php`
- Create: `apps/api/app/Infrastructure/Draws/Providers/LotteryDrawProviderResolver.php`
- Modify: `apps/api/.env.example`
- Modify: `docker-compose.yml`
- Modify: `docker-compose.prod.yml`
- Test: `apps/api/tests/Unit/Draws/ProviderSecretSanitizerTest.php`
- Test: `apps/api/tests/Feature/Draws/ProviderConfigurationTest.php`

- [ ] Escribir pruebas que sustituyan `LOTTERY_API_KEY` en URL, excepciones, arrays JSON anidados, cabeceras y cookies por `[REDACTED]`, sin registrar el valor original.
- [ ] Añadir configuración con las variables solicitadas, incluyendo compatibilidad con `LOTTERY_API_KEY` y los nombres genéricos de la especificación inicial. La clave y base URL permanecen vacías en `.env.example`.
- [ ] Registrar el fake como predeterminado para `testing`/CI y resolver el HTTP real solo por `LOTTERY_API_PROVIDER=elboletoganador`; si está desactivado, no hacer solicitudes salvo `--force` manual.
- [ ] Pasar únicamente nombres de variables a Compose/Dokploy; no incorporar clave, URL privada ni fixture sensible. Asegurar que el provider no se expone a Resources.
- [ ] Ejecutar tests de seguridad/configuración y revisar solo archivos rastreados con `git grep -Il -E 'api\.elboletoganador\.com/api/sorteos/.{8,}|LOTTERY_API_KEY=.+'`; informar exclusivamente nombres de archivo, nunca líneas ni valores coincidentes.
- [ ] Commit: `feat: configure secure draw providers`.

### Task 3: Fake provider y fixtures deterministas sin red

**Files:**
- Create: `apps/api/app/Infrastructure/Draws/Providers/FakeLotteryDrawProvider.php`
- Create: `apps/api/tests/Fixtures/Draws/elboletoganador/{available,corrected,invalid-two-prizes,invalid-number,unknown-lottery}.json`
- Test: `apps/api/tests/Feature/Draws/FakeLotteryDrawProviderTest.php`

- [ ] Escribir fixtures sanitizadas de objeto directo con `id`, `loteria_id`, `fecha_sorteo`, `premios` y `hora`; no incluir clave ni URL con clave.
- [ ] Escribir pruebas del fake para `available`, duplicado, corrección, `[]`/`null` como pendiente, payload inválido, timeout, 401, 403, 429 y 500; confirmar sus capacidades de fecha y rango.
- [ ] Implementar escenario injectable mediante configuración/constructor de prueba, sin llamar a Internet.
- [ ] Ejecutar test del fake con `php artisan test tests/Feature/Draws/FakeLotteryDrawProviderTest.php`; confirmar PASS sin conectividad externa.
- [ ] Commit: `test: add deterministic fake draw provider`.

### Task 4: Adaptador HTTP de El Boleto Ganador y resultado pendiente

**Files:**
- Create: `apps/api/app/Infrastructure/Draws/Providers/HttpLotteryDrawProvider.php`
- Create: `apps/api/app/Infrastructure/Draws/Exceptions/SafeProviderException.php`
- Test: `apps/api/tests/Feature/Draws/HttpLotteryDrawProviderTest.php`

- [ ] Escribir tests con `Http::fake()` que verifiquen el host y ruta reales `https://api.elboletoganador.com/api/sorteos/{api_key}/4` sin revelar la clave; comprobar que la representación para logs es `/api/sorteos/[REDACTED]/4`. Cubrir objeto disponible, `null`/`[]` pendiente, lista no vacía inválida, fecha anterior pendiente, timeout, HTTP 400/401/403/404/408/422/429/500/502/503/504 y `Retry-After`.
- [ ] Implementar solo capacidad `supportsCurrentDraw=true`; rechazar explícitamente fecha histórica, rango y reconciliación antes de `Http::get`.
- [ ] Construir la ruta con la clave únicamente en memoria, capturar toda excepción de HTTP y convertirla a `DrawFetchResult::failure` o `SafeProviderException`; ningún mensaje, tag o excepción conserva la ruta original.
- [ ] Implementar un único intento HTTP por invocación del adaptador: clasificar 401/403 como autenticación, 429 como rate limit, conexiones como red y no asumir que 404 equivale a pendiente. El job, no el adaptador, programa los reintentos y respeta `Retry-After`.
- [ ] Ejecutar el test y verificar que los registros de log capturados no contienen la clave de prueba.
- [ ] Commit: `feat: add sanitized current-draw HTTP provider`.

### Task 5: Normalizador hacia `NormalizedDrawData`

**Files:**
- Create: `apps/api/app/Application/Draws/Normalization/ProviderPayloadNormalizer.php`
- Create: `apps/api/app/Application/Draws/Normalization/NormalizedPayloadFailure.php`
- Test: `apps/api/tests/Unit/Draws/ProviderPayloadNormalizerTest.php`

- [ ] Escribir pruebas para `04-00-97`, ID remoto, coincidencia de `loteria_id`, fecha/hora Santo Domingo a UTC y hash material estable pese a variar `updated_at`. La antigüedad se prueba en el provider/orquestador antes de normalizar: el normalizador solo devuelve `NormalizedDrawData` o `NormalizedPayloadFailure`.
- [ ] Escribir pruebas que rechacen objeto/envoltorio no contractual, lotería desconocida, ID incompatible, fecha inválida, dos/cuatro premios, `105`, letras y `null`.
- [ ] Implementar normalización exclusivamente a `NormalizedDrawData`, usando `LotteryNumber` en cada posición y una proyección canónica material para SHA-256.
- [ ] Sanitizar recursivamente payload y representaciones no JSON antes de retornarlas para persistencia/cuarentena.
- [ ] Ejecutar unitarias de normalización y las de `LotteryNumber`; confirmar PASS.
- [ ] Commit: `feat: normalize provider draw payloads`.

### Task 6: Persistencia transaccional, corrección y cuarentena

**Files:**
- Create: `apps/api/app/Application/Draws/Persistence/PersistNormalizedDraw.php`
- Create: `apps/api/app/Application/Draws/Persistence/PersistDrawResult.php`
- Create: `apps/api/app/Application/Draws/Events/{DrawConfirmed,DrawCorrected,DrawQuarantined}.php`
- Test: `apps/api/tests/Feature/Draws/PersistNormalizedDrawTest.php`

- [ ] Escribir tests MySQL para alta confirmada, diez duplicados, corrección append-only y hash/status actualizado. Crear además `PersistDrawQuarantine` (o ampliar explícitamente el caso de persistencia) que reciba `NormalizedPayloadFailure`, pruebe dos premios, `105`, lotería desconocida y payload con clave reflejada sanitizado, y actualice la cuarentena/contador en la misma transacción.
- [ ] Implementar `DB::transaction` con `lockForUpdate`: buscar por identidad externa y, solo de faltar, por fecha programada; crear corrección antes de actualizar el sorteo. Implementar la persistencia de `NormalizedPayloadFailure` en la misma frontera de aplicación, sin intentar pasar payload inválido a `PersistNormalizedDraw`.
- [ ] Ante duplicate-key, recargar ambas identidades y revaluar hash. Si resuelven filas distintas, no modificar filas, persistir `SyncError(persistence)`, cuarentena y resultado de conflicto.
- [ ] Incrementar el contador correspondiente en la misma transacción; despachar eventos mediante `afterCommit`.
- [ ] Ejecutar el test de persistencia sobre MySQL 8.4 y confirmar que no hay Draw parcial ni corrección duplicada.
- [ ] Commit: `feat: persist normalized draws idempotently`.

### Task 7: Orquestador, SyncRun, errores y job con lock Redis

**Files:**
- Create: `apps/api/app/Application/Draws/Services/SyncLotteryDraws.php`
- Create: `apps/api/app/Application/Draws/Events/DrawSyncCompleted.php`
- Create: `apps/api/app/Infrastructure/Draws/Jobs/SyncLotteryDrawsJob.php`
- Modify: `apps/api/config/horizon.php`
- Test: `apps/api/tests/Feature/Draws/SyncLotteryDrawsTest.php`
- Test: `apps/api/tests/Feature/Draws/SyncLotteryDrawsJobTest.php`

- [ ] Escribir tests para un único UUID por ejecución/reintentos, transiciones queued/running/succeeded/partial/failed, `not_available` exitoso con metadata pendiente y contadores acumulados. Exigir que cada intento fallido cree exactamente un `SyncError` sanitizado con `attempt` creciente antes de relanzar la excepción segura, sin reiniciar contadores.
- [ ] Cubrir HTTP 401/403 sin reintento infinito, 429 con retraso, 500/timeout reintentables, fallo final seguro y que `/api/health` siga sano.
- [ ] Implementar lock Redis `draw-sync:{provider}:{external-id}:{scope-hash}`, `queue=draw-sync`, `tries`, `timeout`, `backoff` y tags seguros. Configurar Horizon para consumir `draw-sync` y `default`.
- [ ] Conservar el `SyncRun` por UUID en el job. `DrawSyncCompleted` se envía después del commit del terminal; documentar consumidores idempotentes, sin prometer exactly-once.
- [ ] Escribir tres pruebas MySQL independientes: dos jobs bajo lock con un solo Draw; una carrera que simule lock expirado/duplicate-key y se recupere sin corrección; y dos identidades que resuelvan sorteos distintos, creando `SyncError(persistence)` y cuarentena, con estado `failed` si es el único elemento o `partial` si ya hubo un efecto exitoso.
- [ ] Ejecutar ambos tests y confirmar PASS con Redis/MySQL reales.
- [ ] Commit: `feat: synchronize lottery draws with Redis locking`.

### Task 8: Comandos Artisan y dry-run verificable

**Files:**
- Create: `apps/api/app/Console/Commands/SyncLotteryDraws.php`
- Create: `apps/api/app/Console/Commands/ReconcileLotteryDraws.php`
- Test: `apps/api/tests/Feature/Draws/SyncDrawCommandsTest.php`

- [ ] Escribir pruebas para `draws:sync --provider=fake --lottery=5`, fecha, rango, proveedor real actual, lotería inexistente, rango invertido, `--force` y capacidades no soportadas. Para el real, `--date`, `--from/--to`, `draws:reconcile` y omitir `--lottery` deben rechazar antes de crear `SyncRun`, encolar job o llamar HTTP; `--force` no altera esa regla.
- [ ] Probar `--dry-run`: ejecuta sin cola, deja un SyncRun `dry_run=true`, incrementa solo `items_received`, deja los otros contadores en cero, no toca Draw/corrección/cuarentena ni emite eventos; disponible/pending terminan succeeded e inválido/fallo permanente terminan failed creando el `SyncError` sanitizado requerido.
- [ ] Implementar comandos que validen `--lottery` como `external_id`, deleguen al mismo orquestador y muestren resumen sanitizado.
- [ ] Hacer que `draws:reconcile` use el mismo caso de uso y las mismas opciones admitidas; para proveedor real rechaza sin HTTP las fechas/rangos, mientras el fake los acepta para pruebas.
- [ ] Ejecutar pruebas CLI y confirmar que la salida no contiene la clave de prueba.
- [ ] Commit: `feat: add manual draw synchronization commands`.

### Task 9: Documentación y superficies de despliegue

**Files:**
- Modify: `docs/API_CONTRACT_TEMPLATE.md`
- Modify: `docs/ENVIRONMENT.md`
- Modify: `docs/DOKPLOY_DEPLOYMENT.md`
- Modify: `docs/phases/PHASE_01A_DRAW_DOMAIN.md`
- Create: `docs/phases/PHASE_01B_DRAW_PROVIDER.md`
- Modify: `README.md`

- [ ] Documentar el objeto directo sanitizado, mapeo, respuesta pendiente, capacidades actuales, comando real y fake, idempotencia, correcciones, cuarentena y política de reintento.
- [ ] Documentar que la clave va en ruta, se reemplaza por `[REDACTED]` y solo se configura fuera de Git; actualizar las tablas de variables con valores seguros.
- [ ] Confirmar que no se documenta scheduler, interfaz ni Fase 1C como implementados.
- [ ] Ejecutar `git diff --check` y búsqueda de secretos contra archivos rastreados.
- [ ] Commit: `docs: document draw provider operations`.

### Task 10: Regresión completa, Docker, CI y PR

**Files:**
- Modify only if required by a failing verification: `.github/workflows/ci.yml`
- Test: todas las suites existentes.

- [ ] Ejecutar en `apps/api`: `composer validate --strict`, `vendor/bin/pint --test`, `vendor/bin/phpstan analyse --memory-limit=512M` y `php artisan test` con PHP 8.4/MySQL 8.4.
- [ ] Ejecutar en `apps/web`: `npm run lint`, `npm run typecheck`, `npm run test -- --run`, `npm run build`.
- [ ] Ejecutar `docker compose config --quiet`, `docker compose build`, `docker compose up -d --wait`, E2E con propietario efímero y `docker compose -f docker-compose.prod.yml config --quiet && docker compose -f docker-compose.prod.yml build`.
- [ ] Verificar que CI selecciona `FakeLotteryDrawProvider` y no necesita Internet ni `LOTTERY_API_KEY`.
- [ ] Revisar `git status`, conservar sin stage el workbook y ZIP ajenos, subir `codex/phase-01b-draw-provider` y abrir PR `feat: integrate idempotent lottery draw provider`.
- [ ] Esperar `backend`, `frontend` y `containers-and-e2e` verdes antes de declarar finalizada la fase.
