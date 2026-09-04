<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Draws\Queries\GetSyncRun;
use App\Application\Draws\Queries\ListSyncRuns;
use App\Http\Controllers\Controller;
use App\Http\Resources\SyncRunResource;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SyncRunController extends Controller
{
    public function index(Request $request, ListSyncRuns $listSyncRuns): AnonymousResourceCollection
    {
        $validated = $request->validate(['per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);

        return SyncRunResource::collection($listSyncRuns->handle($validated['per_page'] ?? 25));
    }

    public function show(SyncRun $syncRun, GetSyncRun $getSyncRun): SyncRunResource
    {
        return new SyncRunResource($getSyncRun->handle($syncRun));
    }
}
