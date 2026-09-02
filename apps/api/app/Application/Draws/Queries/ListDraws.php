<?php

namespace App\Application\Draws\Queries;

use App\Domain\Draws\Enums\DrawStatus;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListDraws
{
    /**
     * @param  array{lottery_id?: int, external_id?: int, from?: string, to?: string, status?: DrawStatus}  $filters
     * @return LengthAwarePaginator<int, Draw>
     */
    public function handle(array $filters, int $perPage): LengthAwarePaginator
    {
        return Draw::query()->with('lottery')
            ->when($filters['lottery_id'] ?? null, fn ($query, int $lotteryId) => $query->where('lottery_id', $lotteryId))
            ->when($filters['external_id'] ?? null, fn ($query, int $externalId) => $query->whereHas('lottery', fn ($lotteryQuery) => $lotteryQuery->where('external_id', $externalId)))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->whereDate('draw_date_local', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->whereDate('draw_date_local', '<=', $to))
            ->when($filters['status'] ?? null, fn ($query, DrawStatus $status) => $query->where('status', $status))
            ->orderByDesc('draw_date_local')->orderByDesc('scheduled_at_utc')->orderByDesc('id')->paginate($perPage);
    }
}
