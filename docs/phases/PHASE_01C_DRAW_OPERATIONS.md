# Fase 1C: operaciones de sorteos

La sincronización automática está desactivada por defecto. Para habilitarla, configure `LOTTERY_SYNC_AUTOMATIC_ENABLED=true` y el provider requerido. `fake` permite trabajo local sin red; `elboletoganador` utiliza la clave existente fuera de Git y solo consulta el sorteo actual.

El scheduler ejecuta `php artisan draws:dispatch-current` cada minuto, pero el intervalo efectivo se controla con `LOTTERY_SYNC_INTERVAL_MINUTES`. La operación se observa en `/sorteos`. El historial ya existente no se descarga, reemplaza ni recalcula desde esta API.
