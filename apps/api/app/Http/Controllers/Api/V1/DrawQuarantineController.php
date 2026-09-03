<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\DrawQuarantine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DrawQuarantineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['lottery_id' => ['nullable', 'integer'], 'resolved' => ['nullable', 'boolean'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $items = DrawQuarantine::query()->when(isset($data['lottery_id']), fn ($query) => $query->where('lottery_id', $data['lottery_id']))->when(isset($data['resolved']), fn ($query) => $data['resolved'] ? $query->whereNotNull('resolved_at') : $query->whereNull('resolved_at'))->when(isset($data['from']), fn ($query) => $query->whereDate('created_at', '>=', $data['from']))->when(isset($data['to']), fn ($query) => $query->whereDate('created_at', '<=', $data['to']))->latest()->paginate($data['per_page'] ?? 20);

        return response()->json($items->through(fn (DrawQuarantine $item): array => ['id' => $item->id, 'lottery_id' => $item->lottery_id, 'sync_run_id' => $item->sync_run_id, 'error_code' => $item->error_code, 'validation_errors' => $item->validation_errors, 'resolved_at' => $item->resolved_at, 'created_at' => $item->created_at]));
    }
}
