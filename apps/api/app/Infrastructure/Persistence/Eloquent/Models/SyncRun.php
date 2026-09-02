<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Draws\Enums\SyncRunStatus;
use App\Domain\Draws\Enums\SyncTrigger;
use Database\Factories\SyncRunFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property SyncTrigger $trigger
 * @property SyncRunStatus $status
 * @property Carbon|null $requested_from
 * @property Carbon|null $requested_to
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class SyncRun extends Model
{
    /** @use HasFactory<SyncRunFactory> */
    use HasFactory;

    protected $fillable = ['uuid', 'provider', 'trigger', 'lottery_id', 'requested_from', 'requested_to', 'status', 'items_received', 'items_inserted', 'items_updated', 'items_unchanged', 'items_quarantined', 'http_status', 'started_at', 'finished_at', 'duration_ms', 'metadata'];

    protected function casts(): array
    {
        return ['trigger' => SyncTrigger::class, 'status' => SyncRunStatus::class, 'requested_from' => 'date', 'requested_to' => 'date', 'items_received' => 'integer', 'items_inserted' => 'integer', 'items_updated' => 'integer', 'items_unchanged' => 'integer', 'items_quarantined' => 'integer', 'http_status' => 'integer', 'started_at' => 'datetime', 'finished_at' => 'datetime', 'duration_ms' => 'integer', 'metadata' => 'array'];
    }

    /** @return Factory<SyncRun> */
    protected static function newFactory(): Factory
    {
        return SyncRunFactory::new();
    }

    /** @return BelongsTo<Lottery, $this> */
    public function lottery(): BelongsTo
    {
        return $this->belongsTo(Lottery::class);
    }

    /** @return HasMany<SyncError, $this> */
    public function errors(): HasMany
    {
        return $this->hasMany(SyncError::class);
    }

    /** @return HasMany<DrawQuarantine, $this> */
    public function quarantines(): HasMany
    {
        return $this->hasMany(DrawQuarantine::class);
    }
}
