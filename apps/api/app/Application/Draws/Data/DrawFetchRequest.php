<?php

namespace App\Application\Draws\Data;

use App\Domain\Draws\Enums\SyncTrigger;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DrawFetchRequest
{
    public function __construct(
        public string $provider,
        public int $lotteryExternalId,
        public SyncTrigger $trigger,
        public ?DateTimeImmutable $date = null,
        public ?DateTimeImmutable $rangeStart = null,
        public ?DateTimeImmutable $rangeEnd = null,
    ) {
        if ($this->lotteryExternalId <= 0) {
            throw new InvalidArgumentException('The lottery external ID must be positive.');
        }

        if ($this->date !== null && ($this->rangeStart !== null || $this->rangeEnd !== null)) {
            throw new InvalidArgumentException('A date request cannot include a date range.');
        }

        if (($this->rangeStart === null) !== ($this->rangeEnd === null)) {
            throw new InvalidArgumentException('A date range requires both start and end dates.');
        }

        if ($this->rangeStart !== null && $this->rangeEnd !== null && $this->rangeStart > $this->rangeEnd) {
            throw new InvalidArgumentException('The date range start cannot be after its end.');
        }
    }
}
