<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Draws\Enums\SyncErrorType;
use Database\Factories\SyncErrorFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncError extends Model
{
    /** @use HasFactory<SyncErrorFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['sync_run_id', 'lottery_id', 'type', 'message', 'http_status', 'retryable', 'attempt', 'safe_context', 'occurred_at', 'resolved_at'];

    protected function casts(): array
    {
        return ['type' => SyncErrorType::class, 'http_status' => 'integer', 'retryable' => 'boolean', 'attempt' => 'integer', 'safe_context' => 'array', 'occurred_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    /** @return Factory<SyncError> */
    protected static function newFactory(): Factory
    {
        return SyncErrorFactory::new();
    }

    /** @return BelongsTo<SyncRun, $this> */
    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }

    /** @return BelongsTo<Lottery, $this> */
    public function lottery(): BelongsTo
    {
        return $this->belongsTo(Lottery::class);
    }
}
