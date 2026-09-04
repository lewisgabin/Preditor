<?php

namespace App\Application\Draws\Queries;

use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListSyncRuns
{
    /** @return LengthAwarePaginator<int, SyncRun> */
    public function handle(int $perPage = 25): LengthAwarePaginator
    {
        return SyncRun::query()->orderByRaw('COALESCE(started_at, created_at) DESC')->orderByDesc('id')->paginate($perPage);
    }
}
