<?php

namespace App\Http\Controllers\Api\V1;

use App\Application\Draws\Services\DispatchCurrentDrawSyncs;
use App\Domain\Draws\Enums\SyncTrigger;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ManualSyncRunController extends Controller
{
    public function store(Request $request, DispatchCurrentDrawSyncs $dispatcher): JsonResponse
    {
        $data = $request->validate(['lottery_external_ids' => ['sometimes', 'array'], 'lottery_external_ids.*' => ['integer', 'min:1']]);
        $ids = $data['lottery_external_ids'] ?? null;
        if ($ids !== null && Lottery::query()->where('is_active', true)->whereIn('external_id', $ids)->count() !== count(array_unique($ids))) {
            return response()->json(['message' => 'Solo se permiten loterías activas registradas.'], 422);
        }
        $summary = $dispatcher->handle($ids, SyncTrigger::Manual);

        return response()->json(['data' => ['sync_run_uuids' => $summary['run_uuids']]], 202);
    }
}
