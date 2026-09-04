<?php

namespace App\Application\Strategies;

use App\Infrastructure\Persistence\Eloquent\Models\Method;
use App\Infrastructure\Persistence\Eloquent\Models\MethodVersion;
use Illuminate\Support\Facades\DB;

final class CreateMethodVersion
{
    /** @param array<string, mixed> $attributes */
    public function __invoke(Method $method, array $attributes): MethodVersion
    {
        return DB::transaction(function () use ($method, $attributes): MethodVersion {
            $locked = Method::query()->lockForUpdate()->findOrFail($method->id);
            unset($attributes['id'], $attributes['method_id'], $attributes['version']);

            return $locked->versions()->create($attributes + ['version' => (int) $locked->versions()->max('version') + 1]);
        }, 3);
    }
}
