<?php

declare(strict_types=1);

namespace App\Application\Health;

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
        ];
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
