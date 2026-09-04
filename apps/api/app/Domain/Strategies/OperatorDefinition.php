<?php

namespace App\Domain\Strategies;

use InvalidArgumentException;

final readonly class OperatorDefinition
{
    public function __construct(public OperatorType $type, public PrizePosition $first, public ?PrizePosition $second = null, public int $constant = 0, public int $firstDigit = 1, public int $secondDigit = 1)
    {
        if ($constant < 0 || $constant > 99 || ! in_array($firstDigit, [0, 1], true) || ! in_array($secondDigit, [0, 1], true)) {
            throw new InvalidArgumentException('Argumentos de operador inválidos.');
        }
        if (in_array($type, [OperatorType::SubtractPositions, OperatorType::AbsoluteDifference, OperatorType::SumPositions, OperatorType::ConcatUnits, OperatorType::ConcatDigits], true) && $second === null) {
            throw new InvalidArgumentException('El operador requiere dos posiciones.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (array_diff(array_keys($data), ['type', 'first', 'second', 'constant', 'first_digit', 'second_digit']) !== []) {
            throw new InvalidArgumentException('Configuración de operador desconocida.');
        }
        foreach (['constant', 'first_digit', 'second_digit'] as $key) {
            if (isset($data[$key]) && ! is_int($data[$key])) {
                throw new InvalidArgumentException('Los argumentos deben ser enteros.');
            }
        }

        return new self(OperatorType::from($data['type']), PrizePosition::from($data['first']), isset($data['second']) ? PrizePosition::from($data['second']) : null, $data['constant'] ?? 0, $data['first_digit'] ?? 1, $data['second_digit'] ?? 1);
    }
}
