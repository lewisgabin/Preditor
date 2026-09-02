<?php

namespace App\Domain\Draws\ValueObjects;

use InvalidArgumentException;
use Stringable;

final readonly class LotteryNumber implements Stringable
{
    private string $value;

    public function __construct(mixed $value)
    {
        if (is_int($value)) {
            if ($value < 0 || $value > 99) {
                throw new InvalidArgumentException('El número de lotería debe estar entre 00 y 99.');
            }

            $this->value = str_pad((string) $value, 2, '0', STR_PAD_LEFT);

            return;
        }

        if (! is_string($value) || preg_match('/^[0-9]{1,2}$/D', $value) !== 1) {
            throw new InvalidArgumentException('El número de lotería debe ser un entero o texto de uno o dos dígitos.');
        }

        $this->value = str_pad($value, 2, '0', STR_PAD_LEFT);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
