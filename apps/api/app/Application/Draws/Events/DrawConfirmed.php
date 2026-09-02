<?php

namespace App\Application\Draws\Events;

use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;

final readonly class DrawConfirmed
{
    public function __construct(public Draw $draw, public SyncRun $syncRun) {}
}
