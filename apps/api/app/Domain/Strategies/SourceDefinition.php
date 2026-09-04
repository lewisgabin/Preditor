<?php

namespace App\Domain\Strategies;

use InvalidArgumentException;

final readonly class SourceDefinition
{
    public function __construct(public int $lotteryId, public SourceDayRelation $relation)
    {
        if ($lotteryId < 1) {
            throw new InvalidArgumentException('La lotería fuente debe ser válida.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        if (array_diff(array_keys($data), ['lottery_id', 'relation']) !== [] || ! is_int($data['lottery_id'] ?? null) || ! is_string($data['relation'] ?? null)) {
            throw new InvalidArgumentException('Definición de fuente inválida.');
        }

        return new self($data['lottery_id'], SourceDayRelation::from($data['relation']));
    }
}
