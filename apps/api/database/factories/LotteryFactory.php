<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Lottery> */
class LotteryFactory extends Factory
{
    protected $model = Lottery::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return ['external_id' => fake()->unique()->numberBetween(100, 32000), 'name' => $name, 'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'), 'timezone' => 'America/Santo_Domingo', 'is_active' => true, 'sort_order' => fake()->numberBetween(0, 999)];
    }
}
