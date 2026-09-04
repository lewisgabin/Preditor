<?php

use App\Domain\Draws\Enums\SyncRunStatus;
use App\Infrastructure\Draws\Jobs\SyncLotteryDrawsJob;
use App\Infrastructure\Draws\Providers\FakeLotteryDrawProvider;
use App\Infrastructure\Draws\Providers\LotteryDrawProviderResolver;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncError;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->instance(LotteryDrawProviderResolver::class, new LotteryDrawProviderResolver(['fake' => new FakeLotteryDrawProvider], 'fake', true));
});

it('does not queue automatic work when it is disabled', function (): void {
    Queue::fake();
    config()->set('lottery-sync.automatic_enabled', false);
    Lottery::factory()->create(['external_id' => 4]);

    $this->artisan('draws:dispatch-current')->assertSuccessful();

    expect(SyncRun::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('queues only an active lottery missing today and preserves historical draws', function (): void {
    Queue::fake();
    config()->set('lottery-sync.automatic_enabled', true);
    config()->set('lottery-sync.provider', 'fake');
    $lottery = Lottery::factory()->create(['external_id' => 4]);
    $historical = Draw::factory()->for($lottery)->create(['draw_date_local' => '2024-01-01', 'p1' => '04', 'p2' => '00', 'p3' => '97']);

    $this->artisan('draws:dispatch-current')->assertSuccessful();

    expect(SyncRun::query()->count())->toBe(1)->and($historical->fresh()->p1->value())->toBe('04');
    Queue::assertPushed(SyncLotteryDrawsJob::class, 1);
});

it('requires authentication for the operational status and accepts manual sync', function (): void {
    $this->getJson('/api/v1/sync-status')->assertUnauthorized();
    Sanctum::actingAs(User::factory()->create());
    Lottery::factory()->create(['external_id' => 4]);
    Queue::fake();

    $this->postJson('/api/v1/sync-runs', ['lottery_external_ids' => [4]])->assertAccepted()->assertJsonStructure(['data' => ['sync_run_uuids']]);
    $this->postJson('/api/v1/sync-runs', ['lottery_external_ids' => [999]])->assertUnprocessable();
});

it('treats an empty manual lottery list as all active lotteries', function (): void {
    Sanctum::actingAs(User::factory()->create());
    Lottery::factory()->create(['external_id' => 4]);
    Lottery::factory()->create(['external_id' => 5]);
    Queue::fake();

    $this->postJson('/api/v1/sync-runs', ['lottery_external_ids' => []])
        ->assertAccepted()
        ->assertJsonCount(2, 'data.sync_run_uuids');

    Queue::assertPushed(SyncLotteryDrawsJob::class, 2);
});

it('does not duplicate a manual run while one is queued', function (): void {
    Sanctum::actingAs(User::factory()->create());
    $lottery = Lottery::factory()->create(['external_id' => 4]);
    SyncRun::factory()->for($lottery)->queued()->create(['provider' => 'fake']);
    Queue::fake();

    $this->postJson('/api/v1/sync-runs', ['lottery_external_ids' => [4]])->assertAccepted()->assertJsonPath('data.sync_run_uuids', []);
    expect(SyncRun::query()->count())->toBe(1)->and(SyncRun::query()->sole()->status)->toBe(SyncRunStatus::Queued);
});

it('recovers a stale queued run and allows a replacement without touching history', function (): void {
    Queue::fake();
    config()->set('lottery-sync.automatic_enabled', true);
    config()->set('lottery-sync.provider', 'fake');
    config()->set('lottery-sync.stale_after_minutes', 20);
    $lottery = Lottery::factory()->create(['external_id' => 4]);
    $historical = Draw::factory()->for($lottery)->create(['draw_date_local' => '2024-01-01']);
    $stale = SyncRun::factory()->for($lottery)->queued()->create(['provider' => 'fake', 'created_at' => now()->subMinutes(21)]);

    $this->artisan('draws:dispatch-current')->assertSuccessful();

    expect($stale->fresh()->status)->toBe(SyncRunStatus::Failed)
        ->and($stale->fresh()->metadata['stale_recovered'])->toBeTrue()
        ->and(Draw::query()->findOrFail($historical->id)->draw_date_local->toDateString())->toBe('2024-01-01')
        ->and(SyncRun::query()->count())->toBe(2);
    Queue::assertPushed(SyncLotteryDrawsJob::class, 1);
});

it('recovers every stale active run before queueing one replacement', function (): void {
    Queue::fake();
    config()->set('lottery-sync.automatic_enabled', true);
    config()->set('lottery-sync.provider', 'fake');
    config()->set('lottery-sync.stale_after_minutes', 20);
    $lottery = Lottery::factory()->create(['external_id' => 4]);
    $first = SyncRun::factory()->for($lottery)->queued()->create(['provider' => 'fake', 'created_at' => now()->subMinutes(21)]);
    $second = SyncRun::factory()->for($lottery)->running()->create(['provider' => 'fake', 'created_at' => now()->subMinutes(22)]);

    $this->artisan('draws:dispatch-current')->assertSuccessful();

    expect($first->fresh()->status)->toBe(SyncRunStatus::Failed)
        ->and($second->fresh()->status)->toBe(SyncRunStatus::Failed)
        ->and($first->fresh()->finished_at)->not->toBeNull()
        ->and($second->fresh()->finished_at)->not->toBeNull()
        ->and($first->fresh()->metadata['stale_recovered'])->toBeTrue()
        ->and($second->fresh()->metadata['stale_recovered'])->toBeTrue()
        ->and(SyncError::query()->where('sync_run_id', $first->id)->count())->toBe(1)
        ->and(SyncError::query()->where('sync_run_id', $second->id)->count())->toBe(1)
        ->and(SyncRun::query()->where('status', SyncRunStatus::Queued)->count())->toBe(1);
    Queue::assertPushed(SyncLotteryDrawsJob::class, 1);
});

it('preserves every persisted historical draw attribute through operational flows', function (): void {
    Queue::fake();
    config()->set('lottery-sync.automatic_enabled', true);
    config()->set('lottery-sync.provider', 'fake');
    config()->set('lottery-sync.stale_after_minutes', 20);
    $lottery = Lottery::factory()->create(['external_id' => 4]);
    $historical = Draw::factory()->for($lottery)->create(['draw_date_local' => '2024-01-01', 'external_draw_id' => 'history-4', 'p1' => '04', 'p2' => '00', 'p3' => '97']);
    $before = $historical->getRawOriginal();
    $beforeDate = $historical->draw_date_local?->toDateString();
    SyncRun::factory()->for($lottery)->queued()->create(['provider' => 'fake', 'created_at' => now()->subMinutes(21)]);

    $this->artisan('draws:dispatch-current')->assertSuccessful();
    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/sync-runs', ['lottery_external_ids' => [4]])->assertAccepted();

    $reloadedHistorical = Draw::query()->findOrFail($historical->id);
    $after = $reloadedHistorical->getRawOriginal();
    foreach (['id', 'lottery_id', 'provider', 'external_draw_id', 'scheduled_at_utc', 'drawn_at_utc', 'p1', 'p2', 'p3', 'status', 'source_hash', 'raw_payload', 'received_at', 'confirmed_at', 'corrected_at', 'created_at', 'updated_at'] as $attribute) {
        expect($after[$attribute])->toBe($before[$attribute]);
    }
    expect($reloadedHistorical->draw_date_local?->toDateString())->toBe($beforeDate)
        ->toBe('2024-01-01')
        ->and($reloadedHistorical->p1->value())->toBe('04')
        ->and($reloadedHistorical->p2->value())->toBe('00')
        ->and($reloadedHistorical->p3->value())->toBe('97')
        ->and($after['updated_at'])->toBe($before['updated_at']);
});

it('filters errors and resolves them idempotently', function (): void {
    Sanctum::actingAs(User::factory()->create());
    $lottery = Lottery::factory()->create(['external_id' => 4]);
    $run = SyncRun::factory()->for($lottery)->create();
    $error = SyncError::factory()->for($run)->for($lottery)->create(['retryable' => true, 'occurred_at' => '2026-09-03 12:00:00']);
    SyncError::factory()->for($run)->for($lottery)->create(['retryable' => false, 'occurred_at' => '2026-09-01 12:00:00']);

    $this->getJson('/api/v1/sync-errors?retryable=1&from=2026-09-03&to=2026-09-03')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $error->id);
    $this->patchJson('/api/v1/sync-errors/'.$error->id.'/resolve')->assertOk()->assertJsonPath('data.id', $error->id);
    $resolvedAt = $error->fresh()->resolved_at;
    $this->patchJson('/api/v1/sync-errors/'.$error->id.'/resolve')->assertOk();
    expect($error->fresh()->resolved_at->equalTo($resolvedAt))->toBeTrue();
});

it('validates, limits, and orders recent sync runs without exposing metadata', function (): void {
    Sanctum::actingAs(User::factory()->create());
    $lottery = Lottery::factory()->create(['external_id' => 4]);
    SyncRun::factory()->count(6)->for($lottery)->create(['created_at' => now()]);

    $response = $this->getJson('/api/v1/sync-runs?per_page=5')->assertOk()->assertJsonCount(5, 'data');
    expect($response->json('meta.per_page'))->toBe(5)
        ->and($response->json('data.0'))->not->toHaveKey('metadata');
    $this->getJson('/api/v1/sync-runs?per_page=101')->assertUnprocessable();
});
