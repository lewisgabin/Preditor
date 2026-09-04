<?php

namespace App\Http\Resources;

use App\Domain\Strategies\MatchSignalResult;
use App\Domain\Strategies\PrizePosition;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\Signal;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Signal $resource */
class SignalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $signal = $this->resource;
        $snapshot = $signal->calculation_snapshot;
        $target = $signal->targetLottery;
        /** @var Draw|null $draw */
        $draw = $signal->relationLoaded('resultDraw') ? $signal->getRelation('resultDraw') : null;
        $result = $draw === null ? null : [
            'draw_id' => $draw->id, 'p1' => $draw->p1->value(), 'p2' => $draw->p2->value(), 'p3' => $draw->p3->value(),
            'matching_positions' => array_map(fn (PrizePosition $position): string => $position->value, (new MatchSignalResult)->positions($signal->recommended_number, ['P1' => $draw->p1, 'P2' => $draw->p2, 'P3' => $draw->p3])),
        ];

        return ['id' => $signal->id, 'method' => ['code' => $snapshot['method_code'], 'name' => $snapshot['method_name'], 'version' => $snapshot['version']],
            'target' => ['lottery_id' => $target->id, 'external_id' => $target->external_id, 'lottery_name' => $target->name, 'date' => $signal->target_draw_date_local->toDateString()],
            'recommended_number' => $signal->recommended_number->value(), 'status' => $signal->status->value,
            'observed_result' => $result, 'sources' => $snapshot['sources'], 'explanation' => $snapshot['explanation'], 'generated_at' => CarbonImmutable::parse($signal->generated_at->format('Y-m-d H:i:s.u'), 'UTC')->toIso8601String()];
    }
}
