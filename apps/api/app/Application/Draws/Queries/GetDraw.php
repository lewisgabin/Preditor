<?php

namespace App\Application\Draws\Queries;

use App\Infrastructure\Persistence\Eloquent\Models\Draw;

final class GetDraw
{
    public function handle(Draw $draw): Draw
    {
        return $draw->load('lottery');
    }
}
