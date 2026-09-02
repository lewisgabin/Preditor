<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\DrawCorrectionFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrawCorrection extends Model
{
    /** @use HasFactory<DrawCorrectionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['draw_id', 'sync_run_id', 'before_payload', 'after_payload', 'before_hash', 'after_hash', 'detected_at'];

    protected function casts(): array
    {
        return ['before_payload' => 'array', 'after_payload' => 'array', 'detected_at' => 'datetime'];
    }

    /** @return Factory<DrawCorrection> */
    protected static function newFactory(): Factory
    {
        return DrawCorrectionFactory::new();
    }

    /** @return BelongsTo<Draw, $this> */
    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }

    /** @return BelongsTo<SyncRun, $this> */
    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }
}
