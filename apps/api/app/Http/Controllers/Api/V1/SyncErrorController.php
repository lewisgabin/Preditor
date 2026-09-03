<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\SyncError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncErrorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['lottery_id' => ['nullable', 'integer'], 'type' => ['nullable', 'string'], 'retryable' => ['nullable', 'boolean'], 'resolved' => ['nullable', 'boolean'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $errors = SyncError::query()->with('lottery')->when(isset($data['lottery_id']), fn ($query) => $query->where('lottery_id', $data['lottery_id']))->when(isset($data['type']), fn ($query) => $query->where('type', $data['type']))->when(isset($data['retryable']), fn ($query) => $query->where('retryable', $data['retryable']))->when(isset($data['resolved']), fn ($query) => $data['resolved'] ? $query->whereNotNull('resolved_at') : $query->whereNull('resolved_at'))->when(isset($data['from']), fn ($query) => $query->whereDate('occurred_at', '>=', $data['from']))->when(isset($data['to']), fn ($query) => $query->whereDate('occurred_at', '<=', $data['to']))->latest('occurred_at')->paginate($data['per_page'] ?? 20);

        return response()->json($errors);
    }

    public function resolve(SyncError $syncError): JsonResponse
    {
        $syncError->update(['resolved_at' => $syncError->resolved_at ?? now()]);

        return response()->json(['data' => ['id' => $syncError->id, 'resolved_at' => $syncError->resolved_at]]);
    }
}
