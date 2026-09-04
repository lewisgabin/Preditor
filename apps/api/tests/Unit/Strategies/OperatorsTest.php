<?php

use App\Domain\Draws\ValueObjects\LotteryNumber;
use App\Domain\Strategies\OperatorDefinition;
use App\Domain\Strategies\OperatorRegistry;
use App\Infrastructure\Persistence\Eloquent\Casts\LotteryNumberCast;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;

it('calculates approved operators preserving two digits', function (string $type, string $a, string $b, int $constant, string $expected) {
    $definition = OperatorDefinition::fromArray(['type' => $type, 'first' => 'P1', 'second' => 'P3', 'constant' => $constant, 'first_digit' => 0, 'second_digit' => 1]);
    $registry = new OperatorRegistry;
    $prizes = ['P1' => new LotteryNumber($a), 'P3' => new LotteryNumber($b)];
    expect($registry->calculate($definition, $prizes))->toBeInstanceOf(LotteryNumber::class)
        ->and($registry->calculate($definition, $prizes)->value())->toBe($expected)
        ->and($registry->explain($definition, $prizes))->toEndWith(' = '.$expected);
})->with([
    ['add_constant_mod_100', '97', '00', 10, '07'], ['add_constant_mod_100', '00', '00', 10, '10'], ['add_constant_mod_100', '99', '00', 1, '00'],
    ['subtract_constant_mod_100', '03', '00', 5, '98'], ['subtract_constant_mod_100', '00', '00', 11, '89'],
    ['subtract_positions_mod_100', '05', '20', 0, '85'], ['absolute_difference', '10', '90', 0, '80'], ['absolute_difference', '00', '00', 0, '00'],
    ['sum_positions_mod_100', '60', '50', 0, '10'], ['reverse_number', '12', '00', 0, '21'], ['reverse_number', '04', '00', 0, '40'], ['reverse_number', '90', '00', 0, '09'], ['reverse_number', '00', '00', 0, '00'],
    ['concat_unit_digits', '27', '64', 0, '74'], ['concat_specific_digits', '64', '27', 0, '67'], ['identity', '07', '00', 0, '07'],
]);

it('rejects unknown operators and arbitrary arguments', function () {
    expect(fn () => OperatorDefinition::fromArray(['type' => 'php', 'first' => 'P1']))->toThrow(ValueError::class);
    expect(fn () => OperatorDefinition::fromArray(['type' => 'identity', 'first' => 'P1', 'code' => 'arbitrary']))->toThrow(InvalidArgumentException::class);
    expect(fn () => OperatorDefinition::fromArray(['type' => 'add_constant_mod_100', 'first' => 'P1', 'constant' => 1.5]))->toThrow(InvalidArgumentException::class);
});

it('allows Eloquent to write its cached LotteryNumber without changing it', function () {
    $cast = new LotteryNumberCast;
    $model = new Draw;
    $number = $cast->get($model, 'p1', '07', []);
    expect($cast->set($model, 'p1', $number, []))->toBe('07');
});
