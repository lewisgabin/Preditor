<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\Method;
use App\Infrastructure\Persistence\Eloquent\Models\MethodVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Method $resource */
class MethodResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $method = $this->resource;

        return ['id' => $method->id, 'code' => $method->code->value, 'name' => $method->name, 'description' => $method->description,
            'category' => $method->category->value, 'is_active' => $method->is_active,
            'versions' => $method->versions->map(function (MethodVersion $version): array {
                /** @var Lottery $source */
                $source = $version->getRelation('sourceLottery');

                return ['version' => $version->version, 'is_active' => $version->is_active, 'valid_from' => $version->valid_from->toDateString(), 'valid_to' => $version->valid_to?->toDateString(),
                    'target' => ['id' => $version->targetLottery->id, 'name' => $version->targetLottery->name],
                    'source' => ['id' => $source->id, 'name' => $source->name, 'relation' => $version->source_definition['relation']], 'rule' => $version->explanation_template];
            })->values()];
    }
}
