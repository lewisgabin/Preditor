<?php

namespace App\Application\Strategies;

use RuntimeException;

final class GenerationBlocked extends RuntimeException
{
    public function __construct(public readonly string $outcome, public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
