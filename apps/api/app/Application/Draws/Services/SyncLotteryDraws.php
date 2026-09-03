<?php

namespace App\Application\Draws\Services;

use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Data\DrawFetchResult;
use App\Application\Draws\Events\DrawSyncCompleted;
use App\Application\Draws\Normalization\ProviderPayloadNormalizer;
use App\Application\Draws\Persistence\PersistNormalizedDraw;
use App\Domain\Draws\Enums\SyncErrorType;
use App\Domain\Draws\Enums\SyncRunStatus;
use App\Infrastructure\Draws\Exceptions\SafeProviderException;
use App\Infrastructure\Draws\Providers\LotteryDrawProviderResolver;
use App\Infrastructure\Draws\Security\ProviderSecretSanitizer;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncError;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final readonly class SyncLotteryDraws
{
    public function __construct(
        private LotteryDrawProviderResolver $providers,
        private PersistNormalizedDraw $persist,
        private ProviderSecretSanitizer $sanitizer,
    ) {}

    public function createRun(DrawFetchRequest $request): SyncRun
    {
        $lottery = Lottery::query()->where('external_id', $request->lotteryExternalId)->first();

        return SyncRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'provider' => $request->provider,
            'trigger' => $request->trigger,
            'lottery_id' => $lottery?->id,
            'requested_from' => $request->date ?? $request->rangeStart,
            'requested_to' => $request->date ?? $request->rangeEnd,
            'status' => SyncRunStatus::Queued,
            'metadata' => [],
        ]);
    }

    /** @throws SafeProviderException */
    public function run(string $syncRunUuid, DrawFetchRequest $request, int $attempt): void
    {
        $run = $this->markRunning($syncRunUuid);

        if ($run->status !== SyncRunStatus::Running) {
            return;
        }

        try {
            $result = $this->providers->resolve($request->provider)->fetch($request);
        } catch (Throwable $exception) {
            $this->recordFailure($run, $attempt, $exception->getMessage(), null, ['category' => 'network']);

            throw new SafeProviderException('The lottery draw provider request failed.', safeContext: ['category' => 'network']);
        }

        if ($result->status === DrawFetchResult::FAILURE) {
            $this->recordFailure($run, $attempt, (string) $result->failureReason, $result->httpStatus, $result->safeContext);

            throw new SafeProviderException(
                'The lottery draw provider request failed.',
                $result->httpStatus,
                $this->sanitizer->sanitize($result->safeContext),
            );
        }

        if ($result->status === DrawFetchResult::NOT_AVAILABLE) {
            $this->finish($run->uuid, SyncRunStatus::Succeeded, ['result_pending' => true]);

            return;
        }

        $normalizer = new ProviderPayloadNormalizer(
            static fn (int $externalId): bool => Lottery::query()->where('external_id', $externalId)->exists(),
            $this->sanitizer,
        );

        $hadSuccess = false;
        $hadQuarantine = false;
        foreach ($result->payloads as $payload) {
            SyncRun::query()->whereKey($run->id)->increment('items_received');
            $normalized = $normalizer->normalize($payload, $request->provider, $request->lotteryExternalId, new DateTimeImmutable('now'));
            $outcome = ($this->persist)($normalized, $run);
            $hadSuccess = $hadSuccess || in_array($outcome->status, ['inserted', 'updated', 'unchanged'], true);
            $hadQuarantine = $hadQuarantine || in_array($outcome->status, ['quarantined', 'conflict'], true);
        }

        $this->finish($run->uuid, $hadQuarantine ? SyncRunStatus::Partial : SyncRunStatus::Succeeded, [
            'result_pending' => false,
            'had_successful_effect' => $hadSuccess,
        ]);
    }

    public function finishFailure(string $syncRunUuid): void
    {
        $run = SyncRun::query()->where('uuid', $syncRunUuid)->firstOrFail();

        if (in_array($run->status, [SyncRunStatus::Succeeded, SyncRunStatus::Partial, SyncRunStatus::Failed], true)) {
            return;
        }

        $hasEffects = $run->items_inserted > 0 || $run->items_updated > 0 || $run->items_unchanged > 0 || $run->items_quarantined > 0;
        $this->finish($syncRunUuid, $hasEffects ? SyncRunStatus::Partial : SyncRunStatus::Failed);
    }

    public function recordLockContention(string $syncRunUuid, int $attempt): void
    {
        $run = SyncRun::query()->where('uuid', $syncRunUuid)->firstOrFail();
        $this->recordFailure($run, $attempt, 'The draw synchronization lock was not acquired.', null, ['category' => 'lock_contention']);
    }

    private function markRunning(string $syncRunUuid): SyncRun
    {
        return DB::transaction(function () use ($syncRunUuid): SyncRun {
            $run = SyncRun::query()->where('uuid', $syncRunUuid)->lockForUpdate()->firstOrFail();
            if ($run->status === SyncRunStatus::Queued) {
                $run->update(['status' => SyncRunStatus::Running, 'started_at' => now()]);
            }

            return $run->fresh();
        });
    }

    /** @param array<string, mixed> $context */
    private function recordFailure(SyncRun $run, int $attempt, string $message, ?int $httpStatus, array $context): void
    {
        $safeContext = $this->sanitizer->sanitize($context);
        $retryable = $this->retryable($httpStatus, $safeContext);

        DB::transaction(function () use ($run, $attempt, $message, $httpStatus, $safeContext, $retryable): void {
            $lockedRun = SyncRun::query()->lockForUpdate()->findOrFail($run->id);
            SyncError::query()->create([
                'sync_run_id' => $lockedRun->id,
                'lottery_id' => $lockedRun->lottery_id,
                'type' => $this->errorType($httpStatus, $safeContext),
                'message' => (string) $this->sanitizer->sanitize($message),
                'http_status' => $httpStatus,
                'retryable' => $retryable,
                'attempt' => max(1, $attempt),
                'safe_context' => $safeContext,
                'occurred_at' => now(),
            ]);
            $lockedRun->update(['http_status' => $httpStatus]);
        });
    }

    /** @param array<string, mixed> $metadata */
    private function finish(string $syncRunUuid, SyncRunStatus $status, array $metadata = []): void
    {
        DB::transaction(function () use ($syncRunUuid, $status, $metadata): void {
            $run = SyncRun::query()->where('uuid', $syncRunUuid)->lockForUpdate()->firstOrFail();
            if (in_array($run->status, [SyncRunStatus::Succeeded, SyncRunStatus::Partial, SyncRunStatus::Failed], true)) {
                return;
            }

            $startedAt = $run->started_at ?? now();
            $run->update([
                'status' => $status,
                'finished_at' => now(),
                'duration_ms' => max(0, now()->diffInMilliseconds($startedAt)),
                'metadata' => array_merge($run->metadata ?? [], $metadata),
            ]);
            $completed = $run->fresh();
            DB::afterCommit(static fn () => event(new DrawSyncCompleted($completed)));
        });
    }

    /** @param array<string, mixed> $context */
    private function retryable(?int $httpStatus, array $context): bool
    {
        if (in_array($httpStatus, [408, 429, 500, 502, 503, 504], true)) {
            return true;
        }

        return $httpStatus === null && in_array($context['category'] ?? null, ['network', 'timeout', 'lock_contention'], true);
    }

    /** @param array<string, mixed> $context */
    private function errorType(?int $httpStatus, array $context): SyncErrorType
    {
        return match (true) {
            in_array($httpStatus, [401, 403], true), ($context['category'] ?? null) === 'authentication' => SyncErrorType::Authentication,
            $httpStatus === 429, ($context['category'] ?? null) === 'rate_limited' => SyncErrorType::RateLimit,
            $httpStatus === null && in_array($context['category'] ?? null, ['network', 'timeout'], true) => SyncErrorType::Network,
            in_array($httpStatus, [400, 404, 422], true), ($context['category'] ?? null) === 'invalid_payload' => SyncErrorType::Validation,
            default => SyncErrorType::Unknown,
        };
    }
}
