<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Draw $resource */
class DrawResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $draw = $this->resource;

        return ['id' => $draw->id, 'lottery' => $this->whenLoaded('lottery', fn () => ['id' => $draw->lottery->id, 'external_id' => $draw->lottery->external_id, 'name' => $draw->lottery->name]), 'provider' => $draw->provider, 'external_draw_id' => $draw->external_draw_id, 'draw_date_local' => $draw->draw_date_local?->toDateString(), 'scheduled_at_utc' => $draw->scheduled_at_utc?->toISOString(), 'drawn_at_utc' => $draw->drawn_at_utc?->toISOString(), 'p1' => (string) $draw->p1, 'p2' => (string) $draw->p2, 'p3' => (string) $draw->p3, 'status' => $draw->status->value, 'source_hash' => $draw->source_hash, 'received_at' => $draw->received_at?->toISOString(), 'confirmed_at' => $draw->confirmed_at?->toISOString(), 'corrected_at' => $draw->corrected_at?->toISOString(), 'raw_payload' => $this->when($request->routeIs('draws.show'), $draw->raw_payload)];
    }
}
