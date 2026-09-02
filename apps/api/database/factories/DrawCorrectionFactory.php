<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\DrawCorrection;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DrawCorrection> */
class DrawCorrectionFactory extends Factory
{
    protected $model = DrawCorrection::class;

    public function definition(): array
    {
        return ['draw_id' => Draw::factory(), 'sync_run_id' => SyncRun::factory(), 'before_payload' => ['p1' => '00'], 'after_payload' => ['p1' => '01'], 'before_hash' => hash('sha256', 'before'), 'after_hash' => hash('sha256', 'after'), 'detected_at' => now()];
    }
}
