<?php

namespace App\Application\Strategies;

use App\Infrastructure\Persistence\Eloquent\Models\Signal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ReadSignals
{
    /** @return LengthAwarePaginator<int, Signal> */
    public function forDate(string $date): LengthAwarePaginator
    {
        return Signal::query()->with('targetLottery')->whereDate('target_draw_date_local', $date)->orderBy('id')->paginate(100);
    }
}
