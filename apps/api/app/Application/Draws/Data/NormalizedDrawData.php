<?php

namespace App\Application\Draws\Data;

use App\Domain\Draws\Enums\DrawStatus;
use App\Domain\Draws\ValueObjects\LotteryNumber;
use DateTimeImmutable;

final readonly class NormalizedDrawData
{
    /** @param array<string, mixed> $rawPayload */
    public function __construct(
        public int $lotteryExternalId,
        public string $provider,
        public ?string $externalDrawId,
        public DateTimeImmutable $drawDateLocal,
        public ?DateTimeImmutable $scheduledAtUtc,
        public ?DateTimeImmutable $drawnAtUtc,
        public LotteryNumber $p1,
        public LotteryNumber $p2,
        public LotteryNumber $p3,
        public DrawStatus $status,
        public string $sourceHash,
        public array $rawPayload,
        public DateTimeImmutable $receivedAt,
    ) {}
}
