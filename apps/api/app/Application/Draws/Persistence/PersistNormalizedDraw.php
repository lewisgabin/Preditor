<?php

namespace App\Application\Draws\Persistence;

use App\Application\Draws\Data\NormalizedDrawData;
use App\Application\Draws\Events\DrawConfirmed;
use App\Application\Draws\Events\DrawCorrected;
use App\Application\Draws\Events\DrawQuarantined;
use App\Application\Draws\Normalization\NormalizedPayloadFailure;
use App\Domain\Draws\Enums\DrawStatus;
use App\Domain\Draws\Enums\SyncErrorType;
use App\Infrastructure\Draws\Security\ProviderSecretSanitizer;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\DrawCorrection;
use App\Infrastructure\Persistence\Eloquent\Models\DrawQuarantine;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncError;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class PersistNormalizedDraw
{
    public function __construct(
        private PersistDrawQuarantine $persistQuarantine,
        private ProviderSecretSanitizer $sanitizer,
    ) {}

    public function __invoke(NormalizedDrawData|NormalizedPayloadFailure $payload, SyncRun $syncRun): PersistDrawResult
    {
        if ($payload instanceof NormalizedPayloadFailure) {
            return ($this->persistQuarantine)($payload, $syncRun);
        }

        try {
            return DB::transaction(fn (): PersistDrawResult => $this->persist($payload, $syncRun));
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKey($exception)) {
                throw $exception;
            }

            return DB::transaction(fn (): PersistDrawResult => $this->recoverDuplicate($payload, $syncRun));
        }
    }

    private function persist(NormalizedDrawData $data, SyncRun $syncRun): PersistDrawResult
    {
        $run = SyncRun::query()->lockForUpdate()->findOrFail($syncRun->id);
        $lottery = Lottery::query()->where('external_id', $data->lotteryExternalId)->lockForUpdate()->first();

        if ($lottery === null) {
            return ($this->persistQuarantine)(new NormalizedPayloadFailure(
                'unknown_lottery',
                'The provider lottery does not exist.',
                $data->rawPayload,
                $data->lotteryExternalId,
            ), $run);
        }

        $draw = $this->findIdentity($data, $lottery);
        if ($draw === null) {
            $draw = Draw::query()->create($this->drawAttributes($data, $lottery, DrawStatus::Confirmed));
            $run->increment('items_inserted');
            DB::afterCommit(fn () => event(new DrawConfirmed($draw, $run)));

            return PersistDrawResult::inserted($draw);
        }

        return $this->compareAndPersist($data, $run, $lottery, $draw);
    }

    private function recoverDuplicate(NormalizedDrawData $data, SyncRun $syncRun): PersistDrawResult
    {
        $run = SyncRun::query()->lockForUpdate()->findOrFail($syncRun->id);
        $lottery = Lottery::query()->where('external_id', $data->lotteryExternalId)->lockForUpdate()->first();

        if ($lottery === null) {
            return ($this->persistQuarantine)(new NormalizedPayloadFailure('unknown_lottery', 'The provider lottery does not exist.', $data->rawPayload, $data->lotteryExternalId), $run);
        }

        [$external, $scheduled] = $this->findRecoveredIdentities($data, $lottery);

        if ($external !== null && $scheduled !== null && ! $external->is($scheduled)) {
            return $this->persistConflict($data, $run, $lottery, $external, $scheduled);
        }

        $draw = $external ?? $scheduled;
        if ($draw === null) {
            throw new \LogicException('A duplicate draw key could not be recovered.');
        }

        return $this->compareAndPersist($data, $run, $lottery, $draw);
    }

    private function findIdentity(NormalizedDrawData $data, Lottery $lottery): ?Draw
    {
        if ($data->externalDrawId !== null) {
            return Draw::query()
                ->where('lottery_id', $lottery->id)
                ->where('provider', $data->provider)
                ->where('external_draw_id', $data->externalDrawId)
                ->lockForUpdate()
                ->first();
        }

        if ($data->scheduledAtUtc === null) {
            return null;
        }

        return Draw::query()
            ->where('lottery_id', $lottery->id)
            ->where('scheduled_at_utc', $data->scheduledAtUtc)
            ->lockForUpdate()
            ->first();
    }

    /** @return array{0: ?Draw, 1: ?Draw} */
    private function findRecoveredIdentities(NormalizedDrawData $data, Lottery $lottery): array
    {
        $external = $data->externalDrawId === null ? null : Draw::query()
            ->where('lottery_id', $lottery->id)
            ->where('provider', $data->provider)
            ->where('external_draw_id', $data->externalDrawId)
            ->lockForUpdate()
            ->first();

        $scheduled = $data->scheduledAtUtc === null ? null : Draw::query()
            ->where('lottery_id', $lottery->id)
            ->where('scheduled_at_utc', $data->scheduledAtUtc)
            ->lockForUpdate()
            ->first();

        return [$external, $scheduled];
    }

    private function compareAndPersist(NormalizedDrawData $data, SyncRun $run, Lottery $lottery, Draw $draw): PersistDrawResult
    {
        if (hash_equals($draw->source_hash, $data->sourceHash)) {
            $run->increment('items_unchanged');

            return PersistDrawResult::unchanged($draw);
        }

        $correction = DrawCorrection::query()->create([
            'draw_id' => $draw->id,
            'sync_run_id' => $run->id,
            'before_payload' => $draw->raw_payload,
            'after_payload' => $this->sanitizedArray($data->rawPayload),
            'before_hash' => $draw->source_hash,
            'after_hash' => $data->sourceHash,
            'detected_at' => now(),
        ]);

        $draw->fill($this->drawAttributes($data, $lottery, DrawStatus::Corrected));
        $draw->corrected_at = now();
        $draw->save();
        $run->increment('items_updated');
        DB::afterCommit(fn () => event(new DrawCorrected($draw, $correction, $run)));

        return PersistDrawResult::updated($draw);
    }

    private function persistConflict(NormalizedDrawData $data, SyncRun $run, Lottery $lottery, Draw $external, Draw $scheduled): PersistDrawResult
    {
        SyncError::query()->create([
            'sync_run_id' => $run->id,
            'lottery_id' => $lottery->id,
            'type' => SyncErrorType::Persistence,
            'message' => 'Draw identities resolve to different persisted rows.',
            'retryable' => false,
            'attempt' => 1,
            'safe_context' => $this->sanitizedArray([
                'external_draw_id' => $data->externalDrawId,
                'external_draw_row_id' => $external->id,
                'scheduled_draw_row_id' => $scheduled->id,
            ]),
            'occurred_at' => now(),
        ]);

        $quarantine = DrawQuarantine::query()->create([
            'sync_run_id' => $run->id,
            'lottery_id' => $lottery->id,
            'raw_payload' => $this->sanitizedArray($data->rawPayload),
            'error_code' => 'identity_conflict',
            'validation_errors' => ['message' => 'Draw identities resolve to different persisted rows.'],
        ]);
        $run->increment('items_quarantined');
        DB::afterCommit(fn () => event(new DrawQuarantined($quarantine, $run)));

        return PersistDrawResult::conflict($quarantine);
    }

    /** @return array<string, mixed> */
    private function drawAttributes(NormalizedDrawData $data, Lottery $lottery, DrawStatus $status): array
    {
        return [
            'lottery_id' => $lottery->id,
            'provider' => $data->provider,
            'external_draw_id' => $data->externalDrawId,
            'draw_date_local' => $data->drawDateLocal,
            'scheduled_at_utc' => $data->scheduledAtUtc,
            'drawn_at_utc' => $data->drawnAtUtc,
            'p1' => $data->p1->value(),
            'p2' => $data->p2->value(),
            'p3' => $data->p3->value(),
            'status' => $status,
            'source_hash' => $data->sourceHash,
            'raw_payload' => $this->sanitizedArray($data->rawPayload),
            'received_at' => $data->receivedAt,
            'confirmed_at' => $status === DrawStatus::Confirmed ? now() : null,
        ];
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

    private function isDuplicateKey(QueryException $exception): bool
    {
        return $exception->getCode() === '23000' || str_contains($exception->getMessage(), 'Duplicate entry');
    }
}
