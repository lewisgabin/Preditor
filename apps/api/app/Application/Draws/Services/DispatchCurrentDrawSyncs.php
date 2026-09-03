<?php

namespace App\Application\Draws\Services;

use App\Application\Draws\Data\DrawFetchRequest;
use App\Domain\Draws\Enums\SyncRunStatus;
use App\Domain\Draws\Enums\SyncTrigger;
use App\Infrastructure\Draws\Jobs\SyncLotteryDrawsJob;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;

final readonly class DispatchCurrentDrawSyncs
{
    public function __construct(private SyncLotteryDraws $sync) {}

    /** @param list<int>|null $externalIds @return array{evaluated:int,queued:int,available:int,in_progress:int,interval:int,configuration:int,run_uuids:list<string>} */
    public function handle(?array $externalIds = null, SyncTrigger $trigger = SyncTrigger::Scheduled): array
    {
        $summary = ['evaluated' => 0, 'queued' => 0, 'available' => 0, 'in_progress' => 0, 'interval' => 0, 'configuration' => 0, 'run_uuids' => []];
        if ($trigger === SyncTrigger::Scheduled && ! config('lottery-sync.automatic_enabled')) {
            $summary['configuration'] = Lottery::query()->where('is_active', true)->count();

            return $summary;
        }
        $today = new DateTimeImmutable('now', new \DateTimeZone('America/Santo_Domingo'));
        $lotteries = Lottery::query()->where('is_active', true)->when($externalIds !== null, fn ($query) => $query->whereIn('external_id', $externalIds))->orderBy('sort_order')->get();
        foreach ($lotteries as $lottery) {
            $summary['evaluated']++;
            $lock = Cache::lock("draw-dispatch:{$lottery->id}:{$today->format('Y-m-d')}", 30);
            if (! $lock->get()) {
                $summary['in_progress']++;

                continue;
            }
            try {
                $provider = (string) config('lottery-sync.provider');
                $hasTodayDraw = Draw::query()->where('lottery_id', $lottery->id)->whereDate('draw_date_local', $today->format('Y-m-d'))->exists();
                $finalRecheck = $this->shouldFinalRecheck($lottery, $provider, $today);
                if ($hasTodayDraw && ! $finalRecheck && $trigger === SyncTrigger::Scheduled) {
                    $summary['available']++;

                    continue;
                }
                if (SyncRun::query()->where('lottery_id', $lottery->id)->where('provider', $provider)->whereIn('status', [SyncRunStatus::Queued, SyncRunStatus::Running])->exists()) {
                    $summary['in_progress']++;

                    continue;
                }
                $last = SyncRun::query()->where('lottery_id', $lottery->id)->where('provider', $provider)->latest('created_at')->value('created_at');
                if ($last !== null && $last->greaterThan(now()->subMinutes((int) config('lottery-sync.interval_minutes')))) {
                    $summary['interval']++;

                    continue;
                }
                $request = new DrawFetchRequest($provider, $lottery->external_id, $trigger);
                $this->sync->validateRequest($request);
                $run = $this->sync->createRun($request, $finalRecheck ? ['purpose' => 'final_recheck', 'local_date' => $today->format('Y-m-d')] : ['local_date' => $today->format('Y-m-d')]);
                SyncLotteryDrawsJob::dispatch($run->uuid, $request);
                $summary['queued']++;
                $summary['run_uuids'][] = $run->uuid;
            } finally {
                $lock->release();
            }
        }

        return $summary;
    }

    private function shouldFinalRecheck(Lottery $lottery, string $provider, DateTimeImmutable $today): bool
    {
        if (! config('lottery-sync.final_recheck_enabled') || $today->format('H:i') < config('lottery-sync.final_recheck_time')) {
            return false;
        }

        return ! SyncRun::query()
            ->where('lottery_id', $lottery->id)
            ->where('provider', $provider)
            ->where('metadata->purpose', 'final_recheck')
            ->where('metadata->local_date', $today->format('Y-m-d'))
            ->exists();
    }
}
