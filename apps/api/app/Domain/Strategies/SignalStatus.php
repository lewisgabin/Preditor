<?php

namespace App\Domain\Strategies;

enum SignalStatus: string
{
    case Generated = 'generated';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
