<?php

use App\Domain\Draws\ValueObjects\LotteryNumber;

it('normalizes lottery numbers without losing leading zeroes', function (int|string $input, string $expected): void {
    expect((string) new LotteryNumber($input))->toBe($expected);
})->with([[0, '00'], ['01', '01'], [9, '09'], ['09', '09'], [99, '99']]);

it('rejects invalid lottery numbers', function (mixed $input): void {
    expect(fn () => new LotteryNumber($input))->toThrow(InvalidArgumentException::class);
})->with([-1, 100, '100', 'ab', '', null, 1.2, true]);
