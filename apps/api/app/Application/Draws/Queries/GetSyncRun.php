<?php

namespace App\Application\Draws\Queries;

use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;

final class GetSyncRun
{
    public function handle(SyncRun $syncRun): SyncRun
    {
        return $syncRun;
    }
}
