<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Draws\Queries\GetSyncRun;
use App\Application\Draws\Queries\ListSyncRuns;
use App\Http\Controllers\Controller;
use App\Http\Resources\SyncRunResource;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SyncRunController extends Controller
{
    public function index(ListSyncRuns $listSyncRuns): AnonymousResourceCollection
    {
        return SyncRunResource::collection($listSyncRuns->handle());
    }

    public function show(SyncRun $syncRun, GetSyncRun $getSyncRun): SyncRunResource
    {
        return new SyncRunResource($getSyncRun->handle($syncRun));
    }
}
