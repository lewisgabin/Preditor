<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Models\User;
use Database\Factories\DrawQuarantineFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrawQuarantine extends Model
{
    /** @use HasFactory<DrawQuarantineFactory> */
    use HasFactory;

    protected $fillable = ['sync_run_id', 'lottery_id', 'raw_payload', 'error_code', 'validation_errors', 'resolved_at', 'resolved_by'];

    protected function casts(): array
    {
        return ['raw_payload' => 'array', 'validation_errors' => 'array', 'resolved_at' => 'datetime'];
    }

    /** @return Factory<DrawQuarantine> */
    protected static function newFactory(): Factory
    {
        return DrawQuarantineFactory::new();
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

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
