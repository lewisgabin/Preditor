<?php

declare(strict_types=1);

namespace App\Application\Health;

use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class GetHealthStatus
{
    public const SCHEDULER_HEARTBEAT_KEY = 'health:scheduler:last_run';

    /** @return array{status: string, checks: array<string, array{status: string}>, version: mixed, git_sha: mixed} */
    public function __invoke(): array
    {
        $checks = [
            'application' => ['status' => 'ok'],
            'mysql' => ['status' => $this->mysqlIsHealthy() ? 'ok' : 'degraded'],
            'redis' => ['status' => $this->redisIsHealthy() ? 'ok' : 'degraded'],
            'scheduler' => ['status' => $this->schedulerIsHealthy() ? 'ok' : 'degraded'],
        ];

        $healthy = collect($checks)->every(
            static fn (array $check): bool => $check['status'] === 'ok',
        );

        return [
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'version' => config('app.version'),
            'git_sha' => config('app.git_sha'),
            'lottery_sync' => [
                'status' => 'ok',
                'enabled' => (bool) config('lottery-sync.automatic_enabled'),
                'provider' => config('lottery-sync.provider'),
                'last_successful_sync_at' => $this->lastSuccessfulSyncAt(),
            ],
        ];
    }

    private function lastSuccessfulSyncAt(): ?string
    {
        try {
            return SyncRun::query()->whereIn('status', ['succeeded', 'partial'])->latest('finished_at')->value('finished_at')?->toIso8601String();
        } catch (Throwable) {
            return null;
        }
    }

    private function mysqlIsHealthy(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function redisIsHealthy(): bool
    {
        try {
            $response = Redis::connection()->ping();

            return $response === true || strtoupper((string) $response) === 'PONG';
        } catch (Throwable) {
            return false;
        }
    }

    private function schedulerIsHealthy(): bool
    {
        try {
            $heartbeat = Cache::store('redis')->get(self::SCHEDULER_HEARTBEAT_KEY);

            return is_string($heartbeat)
                && CarbonImmutable::parse($heartbeat)->greaterThan(now()->subMinutes(3));
        } catch (Throwable) {
            return false;
        }
    }
}
