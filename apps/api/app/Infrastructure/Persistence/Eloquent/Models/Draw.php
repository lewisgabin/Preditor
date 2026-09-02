<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Draws\Enums\DrawStatus;
use App\Domain\Draws\ValueObjects\LotteryNumber;
use App\Infrastructure\Persistence\Eloquent\Casts\LotteryNumberCast;
use Database\Factories\DrawFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property DrawStatus $status
 * @property LotteryNumber $p1
 * @property LotteryNumber $p2
 * @property LotteryNumber $p3
 * @property Carbon|null $draw_date_local
 * @property Carbon|null $scheduled_at_utc
 * @property Carbon|null $drawn_at_utc
 * @property Carbon|null $received_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $corrected_at
 */
class Draw extends Model
{
    /** @use HasFactory<DrawFactory> */
    use HasFactory;

    protected $fillable = ['lottery_id', 'provider', 'external_draw_id', 'draw_date_local', 'scheduled_at_utc', 'drawn_at_utc', 'p1', 'p2', 'p3', 'status', 'source_hash', 'raw_payload', 'received_at', 'confirmed_at', 'corrected_at'];

    protected function casts(): array
    {
        return ['draw_date_local' => 'date', 'scheduled_at_utc' => 'datetime', 'drawn_at_utc' => 'datetime', 'p1' => LotteryNumberCast::class, 'p2' => LotteryNumberCast::class, 'p3' => LotteryNumberCast::class, 'status' => DrawStatus::class, 'raw_payload' => 'array', 'received_at' => 'datetime', 'confirmed_at' => 'datetime', 'corrected_at' => 'datetime'];
    }

    /** @return Factory<Draw> */
    protected static function newFactory(): Factory
    {
        return DrawFactory::new();
    }

    /** @return BelongsTo<Lottery, $this> */
    public function lottery(): BelongsTo
    {
        return $this->belongsTo(Lottery::class);
    }

    /** @return HasMany<DrawCorrection, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(DrawCorrection::class);
    }
}
