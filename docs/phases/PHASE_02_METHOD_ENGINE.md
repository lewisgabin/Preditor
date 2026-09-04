# Fase 2 — Motor de métodos, versiones y señales

## Arquitectura

`Domain/Strategies` contiene enums, definiciones tipadas y el registro cerrado de
operadores. `Application/Strategies` resuelve fuentes, valida temporalidad y genera
señales en transacciones. Eloquent conserva métodos, versiones, señales y fuentes.
HTTP y Artisan comparten los casos de uso. React presenta las explicaciones del
backend; no calcula fórmulas. No hay liquidación, apuestas, dinero ni backtesting.

## Persistencia y versionado

La migración `2026_09_04_000001_create_method_engine_tables` crea `methods`,
`method_versions`, `signals` y `signal_sources`, con foreign keys restrictivas,
checks y unicidad. `CreateMethodVersion` asigna la siguiente versión bajo bloqueo
del método. Las versiones utilizadas rechazan cambios y eliminación en Eloquent.
Una versión nueva tiene una fecha de vigencia explícita; se elige la mayor versión
activa vigente para la fecha solicitada. Las vigencias son inclusivas.

El catálogo preconfigurado está en `database/seeders/data/methods.json`.
`MethodSeeder` resuelve loterías por external ID y persiste IDs internos. Falla si
falta una lotería y revierte toda la operación. Repetirlo no altera reglas ni
versiones existentes. V1 tiene vigencia desde 1900-01-01 para permitir generación
histórica explícita con los draws existentes; no afirma que el método fuera
conocido o validado estadísticamente en aquella fecha.

## Operadores permitidos

`identity`, `add_constant_mod_100`, `subtract_constant_mod_100`,
`subtract_positions_mod_100`, `absolute_difference`, `sum_positions_mod_100`,
`reverse_number`, `concat_unit_digits`, `concat_specific_digits`.

Entradas: posiciones tipadas P1/P2/P3, constantes enteras 0–99, índices de dígitos
0 (decena) y 1 (unidad). Salida: `LotteryNumber`, siempre `00`–`99`.
El módulo positivo es `((x % 100) + 100) % 100`. Operadores y claves desconocidos
se rechazan; no se ejecutan expresiones almacenadas.

## Catálogo

| Código | Fuente | Día | Regla | Destino |
| --- | --- | --- | --- | --- |
| P01 | Primera Noche | Anterior | (P2 − P1) mod 100 | Nacional |
| P02 | LoteDom | Anterior | P2 + 10 mod 100 | Leidsa |
| P03 | Loteka | Anterior | abs(P1 − P3) | Loteka |
| P04 | Leidsa | Anterior | P3 + 1 mod 100 | Gana Más |
| P05 | Primera Tarde | Anterior | P2 + 10 mod 100 | Quiniela Real |
| P06 | Suerte 6 PM | Anterior | P3 + 2 mod 100 | LoteDom |
| P07 | Leidsa | Anterior | unidad(P1) + unidad(P3) | Primera Noche |
| P08 | Suerte MD | Anterior | reverse(P3) | Primera Tarde |
| P09 | Leidsa | Anterior | (P1 + P3) mod 100 | Suerte MD |
| P10 | Primera Tarde | Mismo | P1 − 5 mod 100 | Suerte 6 PM |
| A01 | Gana Más | Anterior | (P3 − P1) mod 100 | Nacional |
| A02 | Suerte 6 PM | Mismo | P2 + 11 mod 100 | Leidsa |
| A03 | Primera Tarde | Mismo | P2 | Leidsa |
| A04 | Quiniela Real | Anterior | (P1 + P3) mod 100 | Loteka |
| A05 | Suerte 6 PM | Anterior | P2 − 11 mod 100 | Gana Más |
| A06 | Loteka | Anterior | (P1 − P2) mod 100 | Gana Más |
| A07 | Quiniela Real | Anterior | (P1 + P3) mod 100 | Primera Noche |
| A08 | Nacional | Anterior | abs(P1 − P3) | Primera Noche |
| A09 | Primera Tarde | Mismo | decena(P3) + unidad(P1) | Primera Noche |
| A10 | LoteDom | Anterior | abs(P1 − P2) | Primera Tarde |

## Temporalidad y correcciones

Las fechas se interpretan en `America/Santo_Domingo`; los instantes se comparan en
UTC. Se exige exactamente un draw confirmado/corregido de la fecha y lotería
fuente. Nunca se rellena un hueco con otro día ni se escoge arbitrariamente entre
varios draws. Para mismo día se requiere `drawn_at_utc` y un único horario destino
configurado y vigente. Si falta esa evidencia: `source_timing_unknown`.

El corte es el menor entre ahora y el horario destino. Sin horario, para fuentes
del día anterior se usa conservadoramente el inicio del día destino. El sorteo,
su recepción y su confirmación deben ser anteriores al corte. Una corrección
posterior bloquea la generación histórica; no se reconstruyen valores antiguos
interpretando payloads de proveedores. Un draw corregido sin confirmación
verificable también queda bloqueado. Estas omisiones se informan como bloqueos
por temporalidad, no como errores técnicos.

No se siembran horarios reales. Los nombres «Tarde», «Noche» o «6 PM» no prueban
el orden. Las señales históricas nacen vencidas; no se implementa liquidación.

## Idempotencia y snapshot

La identidad es versión + lotería destino + fecha destino: una señal por terna.
La generación bloquea el método y la versión y crea señal y fuentes en una sola
transacción; la restricción única respalda la identidad. Repetir devuelve la
señal existente incluso si cambió el draw. El snapshot conserva código, nombre,
versión, ID real del draw, premios, argumentos, resultado, explicación y corte.
La respuesta lee los premios fuente del snapshot, nunca del draw vivo. Los modelos
rechazan modificar el cálculo y sus fuentes. No hay recalculo por correcciones.

## Entradas y pantallas

Endpoints autenticados: `GET /api/v1/methods`, `GET /api/v1/methods/{method}`,
`GET /api/v1/signals?date=YYYY-MM-DD`, `GET /api/v1/signals/{signal}` y
`POST /api/v1/signals/generate` con `date` y `method_codes` opcional.
Los IDs de detalle son internos. El listado de señales pagina de 100 en 100.
El POST devuelve generadas, existentes, sin fuente, bloqueadas y errores, además
de señales y motivos por método. No recibe reglas ejecutables.

`/metodos` separa principales y alternativas; `/senales` permite elegir fecha y
generar, invalida la consulta y muestra número, destino, fuente y explicación.

```bash
php artisan db:seed --class=MethodSeeder
php artisan signals:generate
php artisan signals:generate --date=2026-09-04
php artisan signals:generate --date=2026-09-04 --method=P02 --dry-run
```

Dry-run calcula y explica sin persistir. Ninguno de estos comandos consulta la
API externa. La generación es explícita, sin nuevo scheduler de señales.

Para E2E en una base local aislada: `php artisan db:seed --class=SignalFixtureSeeder`.
Crea dos draws identificados: la fuente de 2001-01-01 y el resultado destino de
2001-01-02, con coincidencias en segunda y tercera. Nunca reemplaza un draw
existente. Está prohibido en producción y no forma parte del seeder global.

La pantalla `/senales?date=YYYY-MM-DD` conserva la fecha al recargar y solicita automáticamente la generación idempotente al seleccionarla. Muestra un estado de preparación hasta recibir el resultado; no descarga sorteos ni sustituye fuentes ausentes.

Cada señal expone `observed_result` cuando existe un único sorteo confirmado o corregido de su lotería y fecha destino. Incluye P1/P2/P3 y `matching_positions`; React resalta todas las coincidencias y sus posiciones. Esta lectura usa el resultado actual disponible y no modifica el snapshot ni utiliza el resultado destino para calcular la señal.
