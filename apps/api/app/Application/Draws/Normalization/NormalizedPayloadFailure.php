<?php

namespace App\Application\Draws\Normalization;

final readonly class NormalizedPayloadFailure
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $code,
        public string $message,
        public array $rawPayload,
        public ?int $lotteryExternalId = null,
    ) {}
}
