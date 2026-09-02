<?php

namespace App\Application\Draws\Data;

use InvalidArgumentException;

final readonly class DrawFetchResult
{
    public const string AVAILABLE = 'available';

    public const string NOT_AVAILABLE = 'not_available';

    public const string FAILURE = 'failure';

    /** @param list<NormalizedDrawData> $draws */
    public function __construct(
        public string $status,
        public array $draws = [],
        public ?string $failureReason = null,
    ) {
        if (! in_array($this->status, [self::AVAILABLE, self::NOT_AVAILABLE, self::FAILURE], true)) {
            throw new InvalidArgumentException('The draw fetch result status is invalid.');
        }
    }

    /** @param list<NormalizedDrawData> $draws */
    public static function available(array $draws): self
    {
        return new self(self::AVAILABLE, $draws);
    }

    public static function notAvailable(): self
    {
        return new self(self::NOT_AVAILABLE);
    }

    public static function failure(string $reason): self
    {
        return new self(self::FAILURE, failureReason: $reason);
    }
}
