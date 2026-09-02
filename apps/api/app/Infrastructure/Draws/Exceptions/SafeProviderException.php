<?php

namespace App\Infrastructure\Draws\Exceptions;

use RuntimeException;

final class SafeProviderException extends RuntimeException
{
    /** @param array<string, scalar|null> $safeContext */
    public function __construct(string $message, public readonly ?int $httpStatus = null, public readonly array $safeContext = [])
    {
        parent::__construct($message, $httpStatus ?? 0);
    }
}
