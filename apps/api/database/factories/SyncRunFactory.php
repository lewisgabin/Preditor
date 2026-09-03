<?php

namespace Database\Factories;

use App\Domain\Draws\Enums\SyncRunStatus;
use App\Domain\Draws\Enums\SyncTrigger;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SyncRun> */
class SyncRunFactory extends Factory
{
    protected $model = SyncRun::class;

    public function definition(): array
    {
        return ['uuid' => (string) Str::uuid(), 'provider' => 'fixture', 'trigger' => SyncTrigger::Manual, 'lottery_id' => Lottery::factory(), 'requested_from' => now()->toDateString(), 'requested_to' => now()->toDateString(), 'status' => SyncRunStatus::Succeeded, 'items_received' => 1, 'items_inserted' => 1, 'items_updated' => 0, 'items_unchanged' => 0, 'items_quarantined' => 0, 'http_status' => 200, 'started_at' => now(), 'finished_at' => now(), 'duration_ms' => 1, 'metadata' => ['source' => 'factory']];
    }

    public function queued(): static
    {
        return $this->state(['status' => SyncRunStatus::Queued, 'started_at' => null, 'finished_at' => null, 'duration_ms' => null]);
    }

    public function running(): static
    {
        return $this->state(['status' => SyncRunStatus::Running, 'finished_at' => null, 'duration_ms' => null]);
    }

    public function partial(): static
    {
        return $this->state(['status' => SyncRunStatus::Partial]);
    }

    public function failed(): static
    {
        return $this->state(['status' => SyncRunStatus::Failed]);
    }
}
