<?php

use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Data\DrawFetchResult;
use App\Application\Draws\Events\DrawSyncCompleted;
use App\Application\Draws\Persistence\PersistNormalizedDraw;
use App\Application\Draws\Services\SyncLotteryDraws;
use App\Domain\Draws\Enums\SyncErrorType;
use App\Domain\Draws\Enums\SyncRunStatus;
use App\Domain\Draws\Enums\SyncTrigger;
use App\Infrastructure\Draws\Exceptions\SafeProviderException;
use App\Infrastructure\Draws\Providers\FakeLotteryDrawProvider;
use App\Infrastructure\Draws\Providers\LotteryDrawProviderResolver;
use App\Infrastructure\Draws\Security\ProviderSecretSanitizer;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncError;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    config(['lottery-api.key' => 'sync-test-secret']);
    $this->lottery = Lottery::factory()->create(['external_id' => 4]);
});

it('keeps one run UUID and accumulated counters across attempts before succeeding', function (): void {
    $calls = 0;
    $sync = synchronizer(new FakeLotteryDrawProvider(responder: static function () use (&$calls): DrawFetchResult {
        $calls++;

        return $calls === 1
            ? DrawFetchResult::failure('The lottery draw provider is temporarily unavailable.', 500, ['token' => 'sync-test-secret'])
            : DrawFetchResult::available([syncPayload()]);
    }));
    $request = syncRequest();
    $run = $sync->createRun($request);

    expect(fn (): mixed => $sync->run($run->uuid, $request, 1))
        ->toThrow(SafeProviderException::class, 'The lottery draw provider request failed.');
    $sync->run($run->uuid, $request, 2);

    $run->refresh();
    $error = SyncError::query()->sole();
    expect($run->status)->toBe(SyncRunStatus::Succeeded)
        ->and($run->uuid)->toBe($run->uuid)
        ->and($run->items_received)->toBe(1)
        ->and($run->items_inserted)->toBe(1)
        ->and($error->attempt)->toBe(1)
        ->and($error->retryable)->toBeTrue()
        ->and(json_encode($error->safe_context, JSON_THROW_ON_ERROR))->not->toContain('sync-test-secret');
});

it('leaves queued runs without a start time until their first attempt', function (): void {
    $sync = synchronizer(new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::notAvailable()));
    $run = $sync->createRun(syncRequest());

    expect($run->status)->toBe(SyncRunStatus::Queued)
        ->and($run->started_at)->toBeNull();

    $sync->run($run->uuid, syncRequest(), 1);
    $run->refresh();
    expect($run->started_at)->not->toBeNull();
});

it('marks a missing current result as succeeded without changing counters', function (): void {
    Event::fake([DrawSyncCompleted::class]);
    $sync = synchronizer(new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::notAvailable()));
    $run = $sync->createRun(syncRequest());

    $sync->run($run->uuid, syncRequest(), 1);

    $run->refresh();
    expect($run->status)->toBe(SyncRunStatus::Succeeded)
        ->and($run->metadata['result_pending'])->toBeTrue()
        ->and($run->items_received)->toBe(0)
        ->and($run->items_inserted)->toBe(0)
        ->and($run->items_updated)->toBe(0)
        ->and($run->items_unchanged)->toBe(0)
        ->and($run->items_quarantined)->toBe(0);
    Event::assertDispatched(DrawSyncCompleted::class);
});

it('records permanent authentication failures without marking them retryable', function (): void {
    $sync = synchronizer(new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::failure('The lottery draw provider rejected the request.', 401, ['authorization' => 'sync-test-secret'])));
    $request = syncRequest();
    $run = $sync->createRun($request);

    expect(fn (): mixed => $sync->run($run->uuid, $request, 1))->toThrow(SafeProviderException::class);
    $sync->finishFailure($run->uuid);

    $run->refresh();
    $error = SyncError::query()->sole();
    expect($run->status)->toBe(SyncRunStatus::Failed)
        ->and($error->type)->toBe(SyncErrorType::Authentication)
        ->and($error->attempt)->toBe(1)
        ->and($error->retryable)->toBeFalse()
        ->and($error->safe_context['authorization'])->toBe('[REDACTED]');
});

it('increments a sanitized error for every failed 500 or timeout attempt before terminal failure', function (DrawFetchResult $failure, bool $retryable): void {
    $sync = synchronizer(new FakeLotteryDrawProvider(defaultResult: $failure));
    $request = syncRequest();
    $run = $sync->createRun($request);

    foreach ([1, 2] as $attempt) {
        expect(fn (): mixed => $sync->run($run->uuid, $request, $attempt))
            ->toThrow(SafeProviderException::class, 'The lottery draw provider request failed.');
    }
    $sync->finishFailure($run->uuid);

    $run->refresh();
    expect($run->status)->toBe(SyncRunStatus::Failed)
        ->and(SyncError::query()->pluck('attempt')->all())->toBe([1, 2])
        ->and(SyncError::query()->pluck('retryable')->all())->toBe([$retryable, $retryable])
        ->and(SyncError::query()->pluck('message')->implode(' '))->not->toContain('sync-test-secret');
})->with([
    '500' => [DrawFetchResult::failure('provider leaked sync-test-secret', 500, ['token' => 'sync-test-secret']), true],
    'timeout' => [DrawFetchResult::failure('provider leaked sync-test-secret', safeContext: ['category' => 'timeout', 'token' => 'sync-test-secret']), true],
]);

it('treats 403 as a permanent authentication failure', function (): void {
    $sync = synchronizer(new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::failure('provider leaked sync-test-secret', 403, ['token' => 'sync-test-secret'])));
    $run = $sync->createRun(syncRequest());

    expect(fn (): mixed => $sync->run($run->uuid, syncRequest(), 1))
        ->toThrow(SafeProviderException::class, 'The lottery draw provider request failed.');
    $sync->finishFailure($run->uuid);

    expect(SyncError::query()->sole()->type)->toBe(SyncErrorType::Authentication)
        ->and(SyncError::query()->sole()->retryable)->toBeFalse();
});

function synchronizer(FakeLotteryDrawProvider $provider): SyncLotteryDraws
{
    return new SyncLotteryDraws(
        new LotteryDrawProviderResolver(['fake' => $provider], 'fake', true),
        app(PersistNormalizedDraw::class),
        new ProviderSecretSanitizer('sync-test-secret'),
    );
}

function syncRequest(): DrawFetchRequest
{
    return new DrawFetchRequest('fake', 4, SyncTrigger::Manual);
}

/** @return array<string, mixed> */
function syncPayload(): array
{
    return ['id' => 227821, 'loteria_id' => 4, 'fecha_sorteo' => '2026-09-01', 'premios' => '04-00-97', 'hora' => '21:01:31'];
}
