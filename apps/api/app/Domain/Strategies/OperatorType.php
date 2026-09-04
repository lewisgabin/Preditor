<?php

namespace App\Domain\Strategies;

enum OperatorType: string
{
    case Identity = 'identity';
    case AddConstant = 'add_constant_mod_100';
    case SubtractConstant = 'subtract_constant_mod_100';
    case SubtractPositions = 'subtract_positions_mod_100';
    case AbsoluteDifference = 'absolute_difference';
    case SumPositions = 'sum_positions_mod_100';
    case Reverse = 'reverse_number';
    case ConcatUnits = 'concat_unit_digits';
    case ConcatDigits = 'concat_specific_digits';
}
