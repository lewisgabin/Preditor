<?php

namespace App\Domain\Strategies;

enum SourceDayRelation: string
{
    case SameDay = 'same_day';
    case PreviousDay = 'previous_day';
}
