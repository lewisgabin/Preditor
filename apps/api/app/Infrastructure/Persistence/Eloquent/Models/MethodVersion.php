<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use App\Domain\Strategies\OperatorDefinition;
use App\Domain\Strategies\SourceDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property array<string, mixed> $source_definition
 * @property array<string, mixed> $operator_definition
 * @property CarbonImmutable $valid_from
 * @property CarbonImmutable|null $valid_to
 */
class MethodVersion extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['source_definition' => 'array', 'operator_definition' => 'array', 'version' => 'integer', 'valid_from' => 'immutable_date', 'valid_to' => 'immutable_date', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            if ($version->exists && $version->isDirty() && $version->signals()->exists()) {
                throw new LogicException('Una versión utilizada es inmutable; cree otra versión.');
            }
            OperatorDefinition::fromArray($version->operator_definition);
            SourceDefinition::fromArray($version->source_definition);
            if (! Lottery::query()->whereKey($version->source_definition['lottery_id'])->exists()) {
                throw new LogicException('La lotería fuente no existe.');
            }
        });
        static::creating(function (self $version): void {
            $expected = (int) self::query()->where('method_id', $version->method_id)->max('version') + 1;
            if ($version->version !== $expected) {
                throw new LogicException('La versión debe ser incremental.');
            }
        });
        static::deleting(function (self $version): void {
            if ($version->signals()->exists()) {
                throw new LogicException('Una versión utilizada no se elimina.');
            }
        });
    }

    /** @return BelongsTo<Method, $this> */
    public function method(): BelongsTo
    {
        return $this->belongsTo(Method::class);
    }

    /** @return BelongsTo<Lottery, $this> */
    public function targetLottery(): BelongsTo
    {
        return $this->belongsTo(Lottery::class);
    }

    /** @return HasMany<Signal, $this> */
    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class);
    }
}
