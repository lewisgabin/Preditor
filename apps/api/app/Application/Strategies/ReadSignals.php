<?php

namespace App\Application\Strategies;

use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\Signal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class ReadSignals
{
    /** @return LengthAwarePaginator<int, Signal> */
    public function forDate(string $date): LengthAwarePaginator
    {
        $signals = Signal::query()->whereDate('target_draw_date_local', $date)->orderBy('id')->paginate(100);
        $this->loadResults(new Collection($signals->items()));

        return $signals;
    }

    /** @param Collection<int, Signal> $signals */
    public function loadResults(Collection $signals): void
    {
        $signals->loadMissing('targetLottery');
        $results = Draw::query()->whereIn('lottery_id', $signals->pluck('target_lottery_id')->unique())
            ->whereIn('draw_date_local', $signals->map(fn (Signal $signal): string => $signal->target_draw_date_local->toDateString())->unique())
            ->whereIn('status', ['confirmed', 'corrected'])->get()
            ->groupBy(fn (Draw $draw): string => $draw->lottery_id.'|'.$draw->draw_date_local?->toDateString());
        foreach ($signals as $signal) {
            $draws = $results->get($signal->target_lottery_id.'|'.$signal->target_draw_date_local->toDateString());
            $signal->setRelation('resultDraw', $draws?->count() === 1 ? $draws->first() : null);
        }
    }
}
