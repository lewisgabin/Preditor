# Fase 1C: operaciones de sorteos

La sincronización automática está desactivada por defecto. Para habilitarla, configure `LOTTERY_SYNC_AUTOMATIC_ENABLED=true` y el provider requerido. `fake` permite trabajo local sin red; `elboletoganador` utiliza la clave existente fuera de Git y solo consulta el sorteo actual.

El scheduler ejecuta `php artisan draws:dispatch-current` cada minuto, pero el intervalo efectivo se controla con `LOTTERY_SYNC_INTERVAL_MINUTES`. La operación se observa en `/sorteos`. El historial ya existente no se descarga, reemplaza ni recalcula desde esta API.

La prueba E2E usa exclusivamente `fake` con la automatización desactivada: inicia una sincronización manual desde `/sorteos` y espera el resultado por estado de interfaz. Para ejecutarla localmente, inicie Compose con `LOTTERY_API_ENABLED=true`, `LOTTERY_API_PROVIDER=fake`, `LOTTERY_SYNC_PROVIDER=fake` y `LOTTERY_SYNC_AUTOMATIC_ENABLED=false`, cree el propietario efímero y ejecute `npm run test:e2e` en `apps/web`.

En `/sorteos`, **Actualizada** significa que existe resultado local para hoy; **Pendiente**, que se consultó sin resultado; **Sincronizando**, que hay un run activo; **Error**, que hay un error abierto; y **Nunca consultada**, que aún no existe operación. La API real conserva el mismo límite: solo actualiza el sorteo actual y nunca modifica el historial existente.
