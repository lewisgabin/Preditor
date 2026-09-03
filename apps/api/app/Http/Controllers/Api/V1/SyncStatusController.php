<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\DrawQuarantine;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\LotterySchedule;
use App\Infrastructure\Persistence\Eloquent\Models\SyncError;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Http\JsonResponse;

class SyncStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'automatic_sync_enabled' => (bool) config('lottery-sync.automatic_enabled'),
            'provider' => config('lottery-sync.provider'),
            'status_refresh_seconds' => (int) config('lottery-sync.status_refresh_seconds'),
            'local_date' => now('America/Santo_Domingo')->toDateString(),
            'last_successful_sync_at' => SyncRun::query()->whereIn('status', ['succeeded', 'partial'])->latest('finished_at')->value('finished_at'),
            'queued_runs' => SyncRun::query()->where('status', 'queued')->count(),
            'running_runs' => SyncRun::query()->where('status', 'running')->count(),
            'open_errors' => SyncError::query()->whereNull('resolved_at')->count(),
            'open_quarantines' => DrawQuarantine::query()->whereNull('resolved_at')->count(),
            'lotteries' => Lottery::query()->where('is_active', true)->orderBy('sort_order')->get()->map(function (Lottery $lottery): array {
                $today = now('America/Santo_Domingo')->toDateString();
                $todayDraw = Draw::query()->where('lottery_id', $lottery->id)->whereDate('draw_date_local', $today)->latest('received_at')->first();
                $latestDraw = Draw::query()->where('lottery_id', $lottery->id)->latest('draw_date_local')->first();
                $latestRun = SyncRun::query()->where('lottery_id', $lottery->id)->latest()->first();
                $openErrors = SyncError::query()->where('lottery_id', $lottery->id)->whereNull('resolved_at')->count();
                $openQuarantines = DrawQuarantine::query()->where('lottery_id', $lottery->id)->whereNull('resolved_at')->count();
                $schedule = LotterySchedule::query()->where('lottery_id', $lottery->id)->where('is_active', true)->where('weekday', now('America/Santo_Domingo')->dayOfWeekIso)->whereDate('effective_from', '<=', $today)->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))->first();
                $status = $todayDraw !== null ? 'updated' : ($latestRun?->status?->value === 'running' ? 'syncing' : ($openErrors > 0 ? 'error' : ($latestRun === null ? 'never_checked' : 'pending')));

                return [
                    'id' => $lottery->id, 'external_id' => $lottery->external_id, 'name' => $lottery->name, 'status' => $status,
                    'today_draw' => $this->draw($todayDraw), 'latest_draw' => $this->draw($latestDraw),
                    'latest_run' => $latestRun === null ? null : ['uuid' => $latestRun->uuid, 'status' => $latestRun->status->value, 'created_at' => $latestRun->created_at],
                    'open_error_count' => $openErrors, 'open_quarantine_count' => $openQuarantines,
                    'schedule' => $schedule === null ? null : ['draw_time_local' => $schedule->draw_time_local, 'sales_close_time_local' => $schedule->sales_close_time_local],
                ];
            })->values(),
        ]]);
    }

    private function draw(?Draw $draw): ?array
    {
        return $draw === null ? null : ['id' => $draw->id, 'date' => $draw->draw_date_local?->toDateString(), 'p1' => (string) $draw->p1, 'p2' => (string) $draw->p2, 'p3' => (string) $draw->p3, 'received_at' => $draw->received_at];
    }
}
