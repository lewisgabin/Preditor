<?php

namespace App\Domain\Strategies;

use App\Domain\Draws\ValueObjects\LotteryNumber;

final class MatchSignalResult
{
    /**
     * @param  array<string, LotteryNumber>  $prizes
     * @return list<PrizePosition>
     */
    public function positions(LotteryNumber $number, array $prizes): array
    {
        return array_values(array_filter(PrizePosition::cases(), fn (PrizePosition $position): bool => $prizes[$position->value]->value() === $number->value()));
    }
}
