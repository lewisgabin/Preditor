<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property SyncRun $resource */
class SyncRunResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $syncRun = $this->resource;

        return ['id' => $syncRun->id, 'uuid' => $syncRun->uuid, 'provider' => $syncRun->provider, 'trigger' => $syncRun->trigger->value, 'lottery_id' => $syncRun->lottery_id, 'requested_from' => $syncRun->requested_from?->toDateString(), 'requested_to' => $syncRun->requested_to?->toDateString(), 'status' => $syncRun->status->value, 'items_received' => $syncRun->items_received, 'items_inserted' => $syncRun->items_inserted, 'items_updated' => $syncRun->items_updated, 'items_unchanged' => $syncRun->items_unchanged, 'items_quarantined' => $syncRun->items_quarantined, 'http_status' => $syncRun->http_status, 'started_at' => $syncRun->started_at?->toISOString(), 'finished_at' => $syncRun->finished_at?->toISOString(), 'duration_ms' => $syncRun->duration_ms];
    }
}
