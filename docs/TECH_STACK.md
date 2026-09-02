# Tecnologías seleccionadas

## Backend

### Laravel 13 + PHP 8.4

Se utilizará para la API, autenticación, tareas programadas, colas, auditoría, backtesting y liquidación de señales.

Motivos:

- Encaja con la experiencia actual del propietario.
- Buen soporte para colas, scheduler, caché, HTTP client, rate limiting y pruebas.
- Permite mantener la lógica de dominio en un único backend.
- Laravel 13 soporta PHP 8.3–8.5; se fija PHP 8.4 para el proyecto.

### Laravel Sanctum

Autenticación de la SPA mediante cookies seguras. No se implementará OAuth hasta existir una necesidad real.

### Redis + Laravel Horizon

- Cola para sincronización, generación de señales, backtests y liquidación.
- Locks distribuidos para impedir procesos duplicados.
- Horizon para observar trabajos, fallos y reintentos.

### MySQL 8.4

Se mantiene compatibilidad con el historial actual y con la experiencia previa del proyecto. El volumen inicial es pequeño para MySQL: alrededor de cientos de miles de sorteos, no miles de millones.

## Frontend

### React 19.2 + TypeScript

SPA orientada a uso móvil, dashboard, tablas, gráficos y control diario.

### Vite 8.2

Build y desarrollo del frontend.

### shadcn/ui 4.19.1 + Tailwind CSS 4.3.3

Los componentes de shadcn/ui se incorporarán como código fuente mantenido por el proyecto, no como una caja negra. Tailwind CSS se integrará mediante `@tailwindcss/vite` y variables CSS semánticas.

Reglas iniciales:

- Inicializar con `shadcn@4.19.1` y fijar todas las versiones efectivas en `package-lock.json`.
- Mantener `components.json` dentro de `apps/web`.
- Priorizar componentes existentes de shadcn/ui antes de crear controles personalizados.
- Usar tokens como `background`, `foreground`, `primary` y `muted` en lugar de colores arbitrarios.
- Construir la interfaz mobile-first y asegurar objetivos táctiles de al menos 44 px.
- Conservar accesibilidad de formularios, diálogos, menús, estados de carga y mensajes de error.

### TanStack Query

Manejará caché de API, reintentos, invalidación y estados de carga. No se almacenarán respuestas completas del servidor en Zustand.

### Zustand

Solo para estado de interfaz local cuando sea necesario: preferencias, paneles abiertos y filtros persistentes.

### Apache ECharts

Gráficas de:

- Capital acumulado.
- ROI mensual.
- Drawdown.
- Resultados por método.
- Heatmaps por día y mes.
- Comparación de portafolios.

## Infraestructura

### Monorepo

Un repositorio facilita:

- Contexto completo para Codex.
- Cambios coordinados entre API y frontend.
- Un solo flujo de issues y PR.
- Despliegue reproducible mediante Docker Compose.

### Docker Compose

Aplicaciones:

- `web`
- `api-nginx`
- `api`
- `horizon`
- `scheduler`

MySQL y Redis se crearán como recursos administrados en Dokploy, con backups y volúmenes separados.

### GitHub Actions

Cada PR ejecutará pruebas, análisis estático y build. `main` se protegerá para impedir merges con checks fallidos.

### Dokploy

Dokploy se conectará al repositorio GitHub y desplegará solamente la rama `main`. La integración automática se habilita después de estabilizar el primer despliegue manual.
