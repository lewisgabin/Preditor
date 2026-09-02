<?php

namespace App\Application\Draws\Persistence;

use App\Application\Draws\Events\DrawQuarantined;
use App\Application\Draws\Normalization\NormalizedPayloadFailure;
use App\Infrastructure\Draws\Security\ProviderSecretSanitizer;
use App\Infrastructure\Persistence\Eloquent\Models\DrawQuarantine;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Support\Facades\DB;

final readonly class PersistDrawQuarantine
{
    public function __construct(private ProviderSecretSanitizer $sanitizer) {}

    public function __invoke(NormalizedPayloadFailure $failure, SyncRun $syncRun): PersistDrawResult
    {
        return DB::transaction(function () use ($failure, $syncRun): PersistDrawResult {
            $lockedRun = SyncRun::query()->lockForUpdate()->findOrFail($syncRun->id);
            $lottery = $failure->lotteryExternalId === null
                ? null
                : Lottery::query()->where('external_id', $failure->lotteryExternalId)->lockForUpdate()->first();

            $quarantine = DrawQuarantine::query()->create([
                'sync_run_id' => $lockedRun->id,
                'lottery_id' => $lottery?->id,
                'raw_payload' => $this->sanitizedArray($failure->rawPayload),
                'error_code' => $failure->code,
                'validation_errors' => ['message' => $this->sanitizer->sanitize($failure->message)],
            ]);

            $lockedRun->increment('items_quarantined');
            DB::afterCommit(fn () => event(new DrawQuarantined($quarantine, $lockedRun)));

            return PersistDrawResult::quarantined($quarantine);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizedArray(array $payload): array
    {
        /** @var array<string, mixed> $sanitized */
        $sanitized = $this->sanitizer->sanitize($payload);

        return $sanitized;
    }
}
