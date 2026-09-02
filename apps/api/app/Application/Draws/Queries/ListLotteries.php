<?php

namespace App\Application\Draws\Queries;

use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListLotteries
{
    /** @return LengthAwarePaginator<int, Lottery> */
    public function handle(int $perPage = 25): LengthAwarePaginator
    {
        return Lottery::query()->orderBy('sort_order')->orderBy('id')->paginate($perPage);
    }
}
