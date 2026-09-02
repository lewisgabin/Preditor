# Especificación de producto

## Usuario inicial

Un administrador privado que estudia métodos de lotería, registra capital y consulta señales.

## Objetivos del MVP

1. Recibir sorteos nuevos automáticamente.
2. Conservar resultados limpios y auditables.
3. Configurar métodos sin cambiar código por cada fórmula simple.
4. Generar señales antes del sorteo destino.
5. Liquidarlas al llegar el resultado.
6. Mostrar ganancias, pérdidas, ROI, rachas y drawdown.
7. Administrar un ciclo de capital con meta y stop-loss.
8. Ejecutar backtests por meses y años sin fuga de información futura.

## No objetivos del MVP

- No colocar apuestas en bancas.
- No recibir depósitos de usuarios.
- No operar una banca.
- No prometer rentabilidad.
- No vender señales públicamente.
- No usar inteligencia artificial para inventar números.
- No permitir fórmulas arbitrarias ejecutables.

## Pantallas principales

### 1. Inicio

- Capital actual.
- Ganancia del ciclo.
- Distancia a meta.
- Margen hasta stop.
- Exposición del día.
- Métodos verdes, amarillos y rojos.
- Próximos sorteos.

### 2. Señales de hoy

Cada señal debe mostrar:

- Método y versión.
- Lotería fuente.
- Resultado fuente.
- Fórmula legible.
- Número calculado.
- Lotería destino.
- Hora límite.
- Apuesta recomendada.
- Estado: pendiente, ganadora, perdedora, vencida o anulada.

### 3. Ciclo de capital

- Capital inicial.
- Reserva protegida.
- Billetera operativa.
- Meta.
- Stop-loss.
- Piso duro.
- Libro diario de movimientos.
- Bloqueo de ganancias.

### 4. Métodos

- Crear borrador.
- Versionar.
- Activar/desactivar.
- Asignar a portafolio.
- Definir fuente, destino, horario, transformación y monto.
- Ver desempeño histórico y futuro por separado.

### 5. Backtests

Filtros:

- Método o portafolio.
- Fecha inicial/final.
- Perfil de pagos.
- Capital.
- Meta y stop.
- Apuesta fija.
- Frecuencia mensual.

Resultados:

- ROI.
- Beneficio.
- Drawdown máximo.
- Meses positivos/negativos.
- Racha sin acierto.
- Metas alcanzadas.
- Stops alcanzados.
- Detalle día por día.

### 6. Sincronización

- Último sorteo recibido por lotería.
- Errores de API.
- Duplicados.
- Registros en cuarentena.
- Reintento manual.

### 7. Palés

- Dos señales para el mismo sorteo.
- Posiciones válidas.
- Perfil de pago.
- Fondo independiente.
- Backtest separado.

## Roles futuros

- Owner.
- Analyst.
- Viewer.

En el MVP solamente se necesita `Owner`, pero la autorización debe quedar preparada con policies.
