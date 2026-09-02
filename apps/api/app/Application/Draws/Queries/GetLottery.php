<?php

namespace App\Application\Draws\Queries;

use App\Infrastructure\Persistence\Eloquent\Models\Lottery;

final class GetLottery
{
    public function handle(Lottery $lottery): Lottery
    {
        return $lottery->load('schedules');
    }
}
