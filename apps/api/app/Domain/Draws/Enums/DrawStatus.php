<?php

namespace App\Domain\Draws\Enums;

enum DrawStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Corrected = 'corrected';
    case Invalid = 'invalid';
}
