<?php

namespace App\Domain\Strategies;

use App\Domain\Draws\ValueObjects\LotteryNumber;

final class OperatorRegistry
{
    /** @param array<string, LotteryNumber> $prizes */
    public function calculate(OperatorDefinition $definition, array $prizes): LotteryNumber
    {
        $a = $prizes[$definition->first->value]->value();
        $b = $definition->second === null ? '00' : $prizes[$definition->second->value]->value();
        $value = match ($definition->type) {
            OperatorType::Identity => $a,
            OperatorType::AddConstant => $this->mod((int) $a + $definition->constant),
            OperatorType::SubtractConstant => $this->mod((int) $a - $definition->constant),
            OperatorType::SubtractPositions => $this->mod((int) $a - (int) $b),
            OperatorType::AbsoluteDifference => abs((int) $a - (int) $b),
            OperatorType::SumPositions => $this->mod((int) $a + (int) $b),
            OperatorType::Reverse => strrev($a),
            OperatorType::ConcatUnits => $a[1].$b[1],
            OperatorType::ConcatDigits => $a[$definition->firstDigit].$b[$definition->secondDigit],
        };

        return new LotteryNumber($value);
    }

    /** @param array<string, LotteryNumber> $prizes */
    public function explain(OperatorDefinition $definition, array $prizes): string
    {
        $a = $prizes[$definition->first->value]->value();
        $b = $definition->second === null ? '00' : $prizes[$definition->second->value]->value();
        $c = $definition->constant;
        $digitA = $definition->firstDigit === 0 ? 'decena' : 'unidad';
        $digitB = $definition->secondDigit === 0 ? 'decena' : 'unidad';
        $formula = match ($definition->type) {
            OperatorType::Identity => $a,
            OperatorType::AddConstant => "$a + $c mod 100",
            OperatorType::SubtractConstant => "$a - $c mod 100",
            OperatorType::SubtractPositions => "$a - $b mod 100",
            OperatorType::AbsoluteDifference => "abs($a - $b)",
            OperatorType::SumPositions => "($a + $b) mod 100",
            OperatorType::Reverse => "reverse($a)",
            OperatorType::ConcatUnits => "unidad($a) + unidad($b)",
            OperatorType::ConcatDigits => "$digitA($a) + $digitB($b)",
        };

        return $formula.' = '.$this->calculate($definition, $prizes)->value();
    }

    private function mod(int $value): int
    {
        return (($value % 100) + 100) % 100;
    }
}
