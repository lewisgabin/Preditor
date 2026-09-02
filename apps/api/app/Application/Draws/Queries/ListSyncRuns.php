<?php

namespace App\Application\Draws\Queries;

use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListSyncRuns
{
    /** @return LengthAwarePaginator<int, SyncRun> */
    public function handle(int $perPage = 25): LengthAwarePaginator
    {
        return SyncRun::query()->orderByDesc('started_at')->orderByDesc('id')->paginate($perPage);
    }
}
