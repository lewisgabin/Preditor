<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Lottery $resource */
class LotteryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $lottery = $this->resource;

        return [
            'id' => $lottery->id,
            'external_id' => $lottery->external_id,
            'name' => $lottery->name,
            'slug' => $lottery->slug,
            'timezone' => $lottery->timezone,
            'is_active' => $lottery->is_active,
            'sort_order' => $lottery->sort_order,
            'schedules' => $this->when($request->routeIs('lotteries.show'), fn () => LotteryScheduleResource::collection($lottery->schedules)),
        ];
    }
}
