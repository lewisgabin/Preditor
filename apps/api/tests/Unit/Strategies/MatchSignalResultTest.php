<?php

use App\Domain\Draws\ValueObjects\LotteryNumber;
use App\Domain\Strategies\MatchSignalResult;
use App\Domain\Strategies\PrizePosition;

it('marks every matching prize position preserving zeroes', function (string $number, array $values, array $expected) {
    $prizes = array_combine(['P1', 'P2', 'P3'], array_map(fn (string $value): LotteryNumber => new LotteryNumber($value), $values));
    $positions = (new MatchSignalResult)->positions(new LotteryNumber($number), $prizes);
    expect(array_map(fn (PrizePosition $position): string => $position->value, $positions))->toBe($expected);
})->with([
    ['07', ['07', '14', '32'], ['P1']],
    ['07', ['14', '07', '32'], ['P2']],
    ['00', ['14', '32', '00'], ['P3']],
    ['07', ['07', '14', '07'], ['P1', 'P3']],
    ['07', ['14', '32', '99'], []],
]);
