<?php

namespace App\Infrastructure\Draws\Jobs;

use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Services\SyncLotteryDraws;
use App\Infrastructure\Draws\Exceptions\SafeProviderException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class SyncLotteryDrawsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'draw-sync';

    public int $timeout = 60;

    private ?int $retryAfterSeconds = null;

    public function __construct(public string $syncRunUuid, public DrawFetchRequest $request)
    {
        $this->onQueue($this->queue);
    }

    public function tries(): int
    {
        return max(1, (int) config('lottery-api.retry_attempts', 3));
    }

    /** @return list<int> */
    public function backoff(): array
    {
        $fallback = max(1, (int) config('lottery-api.retry_backoff_seconds', 5));

        return [$this->retryAfterSeconds ?? $fallback, $fallback * 2, $fallback * 4];
    }

    /** @return list<string> */
    public function tags(): array
    {
        return ['draw-sync', 'provider:'.$this->request->provider, 'lottery:'.$this->request->lotteryExternalId, 'scope:'.$this->scopeHash()];
    }

    public function handle(SyncLotteryDraws $sync): void
    {
        $lock = Cache::lock($this->lockKey(), 120);
        $acquired = false;

        try {
            $lock->block(max(1, $this->timeout - 5));
            $acquired = true;
            $sync->run($this->syncRunUuid, $this->request, $this->attempts());
        } catch (LockTimeoutException) {
            $sync->recordLockContention($this->syncRunUuid, $this->attempts());

            throw new SafeProviderException('The draw synchronization lock timed out.', safeContext: ['category' => 'lock_contention']);
        } catch (SafeProviderException $exception) {
            $this->retryAfterSeconds = $this->retryAfter($exception);
            if (! $this->isRetryable($exception)) {
                $sync->finishFailure($this->syncRunUuid);
                $this->fail($exception);

                return;
            }

            throw $exception;
        } finally {
            if ($acquired) {
                $lock->release();
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        app(SyncLotteryDraws::class)->finishFailure($this->syncRunUuid);
    }

    private function lockKey(): string
    {
        return 'draw-sync:'.$this->request->provider.':'.$this->request->lotteryExternalId.':'.$this->scopeHash();
    }

    private function scopeHash(): string
    {
        return hash('sha256', json_encode([
            'date' => $this->request->date?->format('Y-m-d'),
            'range_start' => $this->request->rangeStart?->format('Y-m-d'),
            'range_end' => $this->request->rangeEnd?->format('Y-m-d'),
        ], JSON_THROW_ON_ERROR));
    }

    private function isRetryable(SafeProviderException $exception): bool
    {
        return in_array($exception->httpStatus, [408, 429, 500, 502, 503, 504], true)
            || ($exception->httpStatus === null && in_array($exception->safeContext['category'] ?? null, ['network', 'timeout', 'lock_contention'], true));
    }

    private function retryAfter(SafeProviderException $exception): ?int
    {
        if ($exception->httpStatus !== 429) {
            return null;
        }

        $retryAfter = $exception->safeContext['retry_after'] ?? null;

        return is_numeric($retryAfter) ? max(1, (int) $retryAfter) : null;
    }
}
