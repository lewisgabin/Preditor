<?php

namespace App\Infrastructure\Persistence\Eloquent\Casts;

use App\Domain\Draws\ValueObjects\LotteryNumber;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** @implements CastsAttributes<LotteryNumber, LotteryNumber|int|string> */
class LotteryNumberCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): LotteryNumber
    {
        return new LotteryNumber($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return $value instanceof LotteryNumber ? $value->value() : (new LotteryNumber($value))->value();
    }
}
