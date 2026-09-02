<?php

namespace App\Domain\Draws\Enums;

enum SyncErrorType: string
{
    case Network = 'network';
    case Authentication = 'authentication';
    case RateLimit = 'rate_limit';
    case Validation = 'validation';
    case Persistence = 'persistence';
    case Unknown = 'unknown';
}
