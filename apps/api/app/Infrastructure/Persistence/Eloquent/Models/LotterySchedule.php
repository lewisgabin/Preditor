<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\LotteryScheduleFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 */
class LotterySchedule extends Model
{
    /** @use HasFactory<LotteryScheduleFactory> */
    use HasFactory;

    protected $fillable = ['lottery_id', 'weekday', 'draw_time_local', 'sales_close_time_local', 'effective_from', 'effective_to', 'is_active'];

    protected function casts(): array
    {
        return ['weekday' => 'integer', 'effective_from' => 'date', 'effective_to' => 'date', 'is_active' => 'boolean'];
    }

    /** @return Factory<LotterySchedule> */
    protected static function newFactory(): Factory
    {
        return LotteryScheduleFactory::new();
    }

    /** @return BelongsTo<Lottery, $this> */
    public function lottery(): BelongsTo
    {
        return $this->belongsTo(Lottery::class);
    }
}
