<?php

namespace App\Http\Resources;

use App\Infrastructure\Persistence\Eloquent\Models\LotterySchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property LotterySchedule $resource */
class LotteryScheduleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $schedule = $this->resource;

        return ['id' => $schedule->id, 'weekday' => $schedule->weekday, 'draw_time_local' => $schedule->draw_time_local, 'sales_close_time_local' => $schedule->sales_close_time_local, 'effective_from' => $schedule->effective_from?->toDateString(), 'effective_to' => $schedule->effective_to?->toDateString(), 'is_active' => $schedule->is_active];
    }
}
