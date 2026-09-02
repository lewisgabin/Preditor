<?php

namespace Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\DrawQuarantine;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DrawQuarantine> */
class DrawQuarantineFactory extends Factory
{
    protected $model = DrawQuarantine::class;

    public function definition(): array
    {
        return ['sync_run_id' => SyncRun::factory(), 'lottery_id' => Lottery::factory(), 'raw_payload' => ['invalid' => true], 'error_code' => 'invalid_payload', 'validation_errors' => ['p1' => ['required']], 'resolved_at' => null, 'resolved_by' => null];
    }
}
