<?php

namespace App\Application\Draws\Data;

final readonly class DrawProviderCapabilities
{
    public function __construct(
        public bool $current,
        public bool $date,
        public bool $range,
    ) {}
}
