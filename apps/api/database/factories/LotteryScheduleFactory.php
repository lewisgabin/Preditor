<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\LotterySchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LotterySchedule> */
class LotteryScheduleFactory extends Factory
{
    protected $model = LotterySchedule::class;

    public function definition(): array
    {
        return ['lottery_id' => Lottery::factory(), 'weekday' => fake()->numberBetween(1, 7), 'draw_time_local' => '12:00:00', 'sales_close_time_local' => '11:50:00', 'effective_from' => now()->toDateString(), 'effective_to' => null, 'is_active' => true];
    }
}
