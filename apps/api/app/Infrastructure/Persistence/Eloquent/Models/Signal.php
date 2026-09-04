<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Draws\ValueObjects\LotteryNumber;
use App\Domain\Strategies\SignalStatus;
use App\Infrastructure\Persistence\Eloquent\Casts\LotteryNumberCast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property LotteryNumber $recommended_number
 * @property SignalStatus $status
 * @property array<string, mixed> $calculation_snapshot
 * @property CarbonImmutable $target_draw_date_local
 * @property CarbonImmutable $generated_at
 * @property CarbonImmutable|null $expires_at
 */
class Signal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['recommended_number' => LotteryNumberCast::class, 'status' => SignalStatus::class, 'calculation_snapshot' => 'array', 'target_draw_date_local' => 'immutable_date', 'generated_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $signal): void {
            if (array_diff(array_keys($signal->getDirty()), ['status', 'updated_at']) !== []) {
                throw new LogicException('El cálculo de una señal es inmutable.');
            }
        });
        static::deleting(function (): void {
            throw new LogicException('Las señales no se eliminan.');
        });
    }

    /** @return BelongsTo<MethodVersion, $this> */
    public function methodVersion(): BelongsTo
    {
        return $this->belongsTo(MethodVersion::class);
    }

    /** @return BelongsTo<Lottery, $this> */
    public function targetLottery(): BelongsTo
    {
        return $this->belongsTo(Lottery::class);
    }

    /** @return HasMany<SignalSource, $this> */
    public function sources(): HasMany
    {
        return $this->hasMany(SignalSource::class);
    }
}
