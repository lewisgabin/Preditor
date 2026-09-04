<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SignalSource extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('La fuente de una señal es inmutable.');
        });
        static::deleting(function (): void {
            throw new LogicException('La fuente de una señal es inmutable.');
        });
    }

    /** @return BelongsTo<Draw, $this> */
    public function draw(): BelongsTo
    {
        return $this->belongsTo(Draw::class);
    }

    /** @return BelongsTo<Signal, $this> */
    public function signal(): BelongsTo
    {
        return $this->belongsTo(Signal::class);
    }
}
