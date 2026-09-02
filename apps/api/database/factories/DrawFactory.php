<?php

namespace Database\Factories;

use App\Domain\Draws\Enums\DrawStatus;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Draw> */
class DrawFactory extends Factory
{
    protected $model = Draw::class;

    public function definition(): array
    {
        return ['lottery_id' => Lottery::factory(), 'provider' => 'fixture', 'external_draw_id' => fake()->unique()->uuid(), 'draw_date_local' => now()->toDateString(), 'scheduled_at_utc' => now(), 'drawn_at_utc' => now(), 'p1' => '00', 'p2' => '01', 'p3' => '09', 'status' => DrawStatus::Confirmed, 'source_hash' => hash('sha256', fake()->uuid()), 'raw_payload' => ['fixture' => true], 'received_at' => now(), 'confirmed_at' => now(), 'corrected_at' => null];
    }

    public function pending(): static
    {
        return $this->state(['status' => DrawStatus::Pending, 'confirmed_at' => null]);
    }

    public function corrected(): static
    {
        return $this->state(['status' => DrawStatus::Corrected, 'corrected_at' => now()]);
    }

    public function invalid(): static
    {
        return $this->state(['status' => DrawStatus::Invalid, 'confirmed_at' => null]);
    }
}
