<?php

namespace Database\Factories;

use App\Domain\Draws\Enums\SyncErrorType;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncError;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SyncError> */
class SyncErrorFactory extends Factory
{
    protected $model = SyncError::class;

    public function definition(): array
    {
        return ['sync_run_id' => SyncRun::factory(), 'lottery_id' => Lottery::factory(), 'type' => SyncErrorType::Validation, 'message' => 'Error de prueba.', 'http_status' => 422, 'retryable' => false, 'attempt' => 1, 'safe_context' => ['field' => 'p1'], 'occurred_at' => now(), 'resolved_at' => null];
    }
}
