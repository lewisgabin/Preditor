# Arquitectura

## Monorepo

```text
quiniela-lab/
├── apps/
│   ├── api/
│   │   ├── app/
│   │   │   ├── Domain/
│   │   │   ├── Application/
│   │   │   ├── Infrastructure/
│   │   │   └── Http/
│   │   └── tests/
│   └── web/
│       ├── src/
│       │   ├── app/
│       │   ├── features/
│       │   ├── entities/
│       │   ├── shared/
│       │   └── pages/
│       └── tests/
├── infra/
│   ├── docker/
│   └── nginx/
├── docs/
├── .github/
├── AGENTS.md
└── docker-compose.yml
```

## Contextos del backend

### Draws

Ingesta, normalización, deduplicación y corrección de sorteos.

### Strategies

Métodos, versiones, operadores y generación de señales.

### Settlement

Liquidación de quiniela y palé.

### Bankroll

Planes, ciclos, límites y ledger.

### Backtesting

Reproducción histórica sin fuga de información.

### Identity

Autenticación, roles y autorización.

## Flujo de datos

```text
API externa
   ↓
SyncLotteryDraws job
   ↓
Validación + normalización
   ↓
draws
   ↓ evento DrawConfirmed
GenerateEligibleSignals listener
   ↓
signals
   ↓ cuando llega el sorteo destino
SettlePendingSignals listener
   ↓
settlements + ledger_entries + snapshots
   ↓
API REST
   ↓
React
```

## Separación de responsabilidades

- React nunca calcula el resultado oficial de una señal.
- Laravel es la fuente de verdad.
- El motor de operadores no conoce HTTP.
- El cliente de la API externa no conoce la interfaz.
- La liquidación no modifica señales ya cerradas.
- El ledger es inmutable; las correcciones se compensan con nuevas entradas.

## API interna

Base:

```text
/api/v1
```

Recursos iniciales:

```text
/auth/*
/lotteries
/draws
/sync-runs
/methods
/method-versions
/portfolios
/signals
/payout-profiles
/bankroll-plans
/cycles
/ledger
/backtests
/dashboard
```

## Actualización de la interfaz

MVP:

- Polling ligero con TanStack Query.
- Invalidación tras acciones.

Posterior:

- Server-Sent Events o WebSockets para señales y liquidaciones en vivo.

## Dominios

Recomendado:

```text
app.ejemplo.com   → frontend
api.ejemplo.com   → Laravel
```

Variables importantes:

```text
APP_URL
FRONTEND_URL
SESSION_DOMAIN
SANCTUM_STATEFUL_DOMAINS
CORS_ALLOWED_ORIGINS
```

Todo tráfico debe usar HTTPS.
