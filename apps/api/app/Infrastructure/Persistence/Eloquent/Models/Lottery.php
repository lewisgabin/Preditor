<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\LotteryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lottery extends Model
{
    /** @use HasFactory<LotteryFactory> */
    use HasFactory;

    protected $fillable = ['external_id', 'name', 'slug', 'timezone', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['external_id' => 'integer', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return Factory<Lottery> */
    protected static function newFactory(): Factory
    {
        return LotteryFactory::new();
    }

    /** @return HasMany<LotterySchedule, $this> */
    public function schedules(): HasMany
    {
        return $this->hasMany(LotterySchedule::class);
    }

    /** @return HasMany<Draw, $this> */
    public function draws(): HasMany
    {
        return $this->hasMany(Draw::class);
    }

    /** @return HasMany<SyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }
}
