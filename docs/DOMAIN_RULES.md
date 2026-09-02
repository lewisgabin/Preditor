# Reglas de dominio

## Sorteos

- Cada lotería conserva el mismo `external_id` utilizado por la API existente.
- Un sorteo se identifica mediante una clave única compatible con el proveedor: preferiblemente `external_draw_id`; como respaldo, lotería + fecha + horario.
- Los premios de quiniela se normalizan a `p1`, `p2`, `p3`, cada uno como texto `00`–`99`.
- Se guarda el payload original y un hash para auditoría.
- Un sorteo puede estar `pending`, `confirmed`, `corrected` o `invalid`.

## Métodos

Un método posee versiones. Una versión define:

- Lotería fuente.
- Momento de la fuente: día anterior o mismo día.
- Posiciones utilizadas.
- Transformación.
- Lotería destino.
- Días elegibles.
- Hora de expiración.
- Perfil de pagos.
- Estado de validación.

Operadores iniciales permitidos:

- Posición directa.
- Volteado.
- Suma módulo 100.
- Resta módulo 100.
- Diferencia absoluta.
- Desplazamiento `+N` o `-N`.
- Combinar decena/unidad de posiciones.
- Combinar unidades de dos posiciones.

No se almacenará PHP, JavaScript ni SQL ejecutable como fórmula.

## Señales

Una señal registra:

- Versión exacta del método.
- Sorteos fuente exactos.
- Número o par calculado.
- Sorteo destino esperado.
- Monto recomendado.
- Fecha de generación.
- Fecha de expiración.
- Explicación de cálculo.
- Estado.

Estados:

```text
pending
won_first
won_second
won_third
lost
expired
cancelled
void
```

Una señal de quiniela se juega una sola vez. No se persigue hasta que salga, salvo que en el futuro exista un tipo de estrategia diferente y explícitamente versionado.

## Perfil de pagos inicial

Editable por banca:

```text
Quiniela:
primera = 70x
segunda = 8x
tercera = 4x

Palé:
primera-segunda = 2000x
primera-tercera = 2000x
segunda-tercera = 100x
```

Debe quedar claro si el multiplicador incluye o no la devolución de la apuesta.

## Capital inicial

```text
capital_total = RD$20,000
reserva_protegida = RD$14,000
billetera_operativa = RD$6,000
meta_ciclo = RD$1,000
stop_loss = RD$600
piso_duro = RD$17,000
meta_temporada = RD$4,000
```

Todos estos valores son configurables y versionados.

## Ciclo

- Comienza en cero.
- Acumula resultados liquidados.
- Al alcanzar la meta, se bloquea el beneficio.
- Al tocar el stop, se suspenden nuevas señales con dinero.
- Un ciclo cerrado no se reabre.
- El modo recomendado es un ciclo por mes.
- No existe martingala.
- El cambio de apuesta se realiza solamente mediante una nueva configuración efectiva desde una fecha futura.

## Backtesting sin fuga futura

En una fecha `D`, el motor solo puede utilizar:

- Sorteos confirmados anteriores a `D`.
- Sorteos del mismo día cuyo horario real sea anterior al destino.
- Configuraciones que ya estaban vigentes en `D`.

Nunca puede:

- Elegir métodos utilizando resultados del período de prueba.
- Recalcular una señal histórica con una versión nueva.
- Usar un sorteo de horario posterior como fuente.
