<?php

use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Data\DrawFetchResult;
use App\Application\Draws\Persistence\PersistNormalizedDraw;
use App\Application\Draws\Services\SyncLotteryDraws;
use App\Domain\Draws\Enums\SyncTrigger;
use App\Infrastructure\Draws\Exceptions\SafeProviderException;
use App\Infrastructure\Draws\Jobs\SyncLotteryDrawsJob;
use App\Infrastructure\Draws\Providers\FakeLotteryDrawProvider;
use App\Infrastructure\Draws\Providers\LotteryDrawProviderResolver;
use App\Infrastructure\Draws\Security\ProviderSecretSanitizer;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\DrawCorrection;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncError;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('makes two deliveries for one run idempotent and uses the draw-sync queue', function (): void {
    Lottery::factory()->create(['external_id' => 4]);
    $sync = new SyncLotteryDraws(
        new LotteryDrawProviderResolver(['fake' => new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::available([jobPayload()]))], 'fake', true),
        app(PersistNormalizedDraw::class),
        new ProviderSecretSanitizer,
    );
    $request = new DrawFetchRequest('fake', 4, SyncTrigger::Manual);
    $run = $sync->createRun($request);
    $first = new SyncLotteryDrawsJob($run->uuid, $request);
    $second = new SyncLotteryDrawsJob($run->uuid, $request);

    $first->handle($sync);
    $second->handle($sync);

    $run->refresh();
    expect($first->queue)->toBe('draw-sync')
        ->and($first->tries())->toBeGreaterThan(0)
        ->and($first->timeout)->toBeGreaterThan(0)
        ->and($first->tags())->not->toContain('sync-test-secret')
        ->and(Draw::query()->count())->toBe(1)
        ->and($run->items_inserted)->toBe(1)
        ->and($run->items_unchanged)->toBe(0);
});

it('releases a rate-limited job using the provider retry-after delay', function (): void {
    Lottery::factory()->create(['external_id' => 4]);
    $sync = new SyncLotteryDraws(
        new LotteryDrawProviderResolver(['fake' => new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::failure('rate limited', 429, ['retry_after' => '17', 'token' => 'sync-test-secret']))], 'fake', true),
        app(PersistNormalizedDraw::class),
        new ProviderSecretSanitizer('sync-test-secret'),
    );
    $request = new DrawFetchRequest('fake', 4, SyncTrigger::Manual);
    $run = $sync->createRun($request);
    $job = (new SyncLotteryDrawsJob($run->uuid, $request))->withFakeQueueInteractions();

    $job->handle($sync);

    $job->assertReleased(17)->assertNotFailed();
    $run->refresh();
    expect($run->status->value)->toBe('running')
        ->and(SyncError::query()->count())->toBe(1)
        ->and(SyncError::query()->sole()->attempt)->toBe(1)
        ->and(json_encode(SyncError::query()->sole()->safe_context, JSON_THROW_ON_ERROR))->not->toContain('sync-test-secret');
});

it('executes a forced current job while the provider is disabled', function (): void {
    Lottery::factory()->create(['external_id' => 4]);
    $sync = new SyncLotteryDraws(
        new LotteryDrawProviderResolver(['fake' => new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::available([jobPayload()]))], 'fake', false),
        app(PersistNormalizedDraw::class),
        new ProviderSecretSanitizer,
    );
    $request = new DrawFetchRequest('fake', 4, SyncTrigger::Manual, force: true);
    $run = $sync->createRun($request);

    (new SyncLotteryDrawsJob($run->uuid, $request))->handle($sync);

    $run->refresh();
    expect(Draw::query()->count())->toBe(1)
        ->and($run->status->value)->toBe('succeeded');
});

it('uses a real Redis lock to serialize a shared draw scope', function (): void {
    Lottery::factory()->create(['external_id' => 4]);
    $request = new DrawFetchRequest('fake', 4, SyncTrigger::Manual);
    $job = new SyncLotteryDrawsJob('00000000-0000-4000-8000-000000000000', $request);
    $lockKey = (new ReflectionMethod($job, 'lockKey'))->invoke($job);
    $first = Cache::lock($lockKey, 10);
    $second = Cache::lock($lockKey, 10);

    expect($first->get())->toBeTrue()
        ->and($second->get())->toBeFalse();
    $first->release();
    expect($second->get())->toBeTrue();
    $second->release();
});

it('makes manual and scheduled requests contend for the same provider lottery scope', function (): void {
    $manual = new SyncLotteryDrawsJob('00000000-0000-4000-8000-000000000001', new DrawFetchRequest('fake', 4, SyncTrigger::Manual));
    $scheduled = new SyncLotteryDrawsJob('00000000-0000-4000-8000-000000000002', new DrawFetchRequest('fake', 4, SyncTrigger::Scheduled));
    $lockKey = (new ReflectionMethod($manual, 'lockKey'))->invoke($manual);

    expect($lockKey)->toBe((new ReflectionMethod($scheduled, 'lockKey'))->invoke($scheduled));
    $owner = Cache::lock($lockKey, 10);
    $contender = Cache::lock((new ReflectionMethod($scheduled, 'lockKey'))->invoke($scheduled), 10);
    expect($owner->get())->toBeTrue()
        ->and($contender->get())->toBeFalse();
    $owner->release();
});

it('executes the second job under real lock contention and records a safe retryable attempt', function (): void {
    Lottery::factory()->create(['external_id' => 4]);
    $sync = new SyncLotteryDraws(
        new LotteryDrawProviderResolver(['fake' => new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::available([jobPayload()]))], 'fake', true),
        app(PersistNormalizedDraw::class),
        new ProviderSecretSanitizer,
    );
    $request = new DrawFetchRequest('fake', 4, SyncTrigger::Manual);
    $run = $sync->createRun($request);
    $job = new SyncLotteryDrawsJob($run->uuid, $request);
    $job->timeout = 6;
    $lockKey = (new ReflectionMethod($job, 'lockKey'))->invoke($job);
    $owner = Cache::lock($lockKey, 10);

    expect($owner->get())->toBeTrue();
    try {
        expect(fn (): mixed => $job->handle($sync))
            ->toThrow(SafeProviderException::class, 'The draw synchronization lock timed out.');
    } finally {
        $owner->release();
    }

    $run->refresh();
    $error = SyncError::query()->sole();
    expect($run->status->value)->toBe('queued')
        ->and(Draw::query()->count())->toBe(0)
        ->and($error->attempt)->toBe(1)
        ->and($error->retryable)->toBeTrue()
        ->and($error->safe_context['category'])->toBe('lock_contention');
});

it('recovers an actual duplicate after an expired Redis lock without a correction', function (): void {
    Lottery::factory()->create(['external_id' => 4]);
    $provider = new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::available([jobPayload()]));
    $sync = new SyncLotteryDraws(
        new LotteryDrawProviderResolver(['fake' => $provider], 'fake', true),
        app(PersistNormalizedDraw::class),
        new ProviderSecretSanitizer,
    );
    $request = new DrawFetchRequest('fake', 4, SyncTrigger::Manual);
    $firstRun = $sync->createRun($request);
    $secondRun = $sync->createRun($request);
    $firstJob = new SyncLotteryDrawsJob($firstRun->uuid, $request);
    $secondJob = new SyncLotteryDrawsJob($secondRun->uuid, $request);
    $lockKey = (new ReflectionMethod($firstJob, 'lockKey'))->invoke($firstJob);
    $expiredLock = Cache::lock($lockKey, 1);

    expect($expiredLock->get())->toBeTrue();
    sleep(2);
    $firstJob->handle($sync);
    $secondJob->handle($sync);

    $secondRun->refresh();
    expect(Draw::query()->count())->toBe(1)
        ->and($secondRun->items_unchanged)->toBe(1);
});

it('recovers a forced duplicate-key after the job acquires an expired Redis lock', function (): void {
    $lottery = Lottery::factory()->create(['external_id' => 4]);
    $provider = new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::available([jobPayload()]));
    $sync = new SyncLotteryDraws(
        new LotteryDrawProviderResolver(['fake' => $provider], 'fake', true),
        app(PersistNormalizedDraw::class),
        new ProviderSecretSanitizer,
    );
    $request = new DrawFetchRequest('fake', 4, SyncTrigger::Manual);
    $run = $sync->createRun($request);
    $job = new SyncLotteryDrawsJob($run->uuid, $request);
    $lockKey = (new ReflectionMethod($job, 'lockKey'))->invoke($job);
    $expiredLock = Cache::lock($lockKey, 1);
    expect($expiredLock->get())->toBeTrue();
    sleep(2);

    config(['database.connections.draw-race' => config('database.connections.mysql')]);
    DB::purge('draw-race');
    $connectionDispatcher = DB::connection()->getEventDispatcher();
    $modelDispatcher = Draw::getEventDispatcher();
    $raceDispatcher = new Dispatcher($this->app);
    DB::connection()->setEventDispatcher($raceDispatcher);
    Draw::setEventDispatcher(new Dispatcher($this->app));
    $inserted = false;
    $raceDispatcher->listen(TransactionRolledBack::class, function () use (&$inserted, $lottery): void {
        if ($inserted) {
            return;
        }

        $inserted = true;
        DB::connection('draw-race')->table('draws')->insert([
            'lottery_id' => $lottery->id, 'provider' => 'fake', 'external_draw_id' => '227821', 'draw_date_local' => '2026-09-01',
            'scheduled_at_utc' => '2026-09-02 01:01:31.000000', 'drawn_at_utc' => '2026-09-02 01:01:31.000000',
            'p1' => '04', 'p2' => '00', 'p3' => '97', 'status' => 'confirmed',
            'source_hash' => hash('sha256', json_encode(['provider' => 'fake', 'external_draw_id' => '227821', 'lottery_external_id' => 4, 'draw_date_local' => '2026-09-01', 'drawn_at_utc' => '2026-09-02T01:01:31+00:00', 'p1' => '04', 'p2' => '00', 'p3' => '97'], JSON_THROW_ON_ERROR)),
            'raw_payload' => json_encode(jobPayload(), JSON_THROW_ON_ERROR), 'received_at' => now(), 'confirmed_at' => now(), 'corrected_at' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);
    });
    Draw::creating(static function (): void {
        throw new QueryException('mysql', 'insert into draws', [], new PDOException('Duplicate entry', 23000));
    });

    try {
        $job->handle($sync);
    } finally {
        Draw::setEventDispatcher($modelDispatcher);
        DB::connection()->setEventDispatcher($connectionDispatcher);
        DB::purge('draw-race');
    }

    $run->refresh();
    expect($inserted)->toBeTrue()
        ->and(Draw::query()->count())->toBe(1)
        ->and(DrawCorrection::query()->count())->toBe(0)
        ->and($run->items_unchanged)->toBe(1);
});

/** @return array<string, mixed> */
function jobPayload(): array
{
    return ['id' => 227821, 'loteria_id' => 4, 'fecha_sorteo' => '2026-09-01', 'premios' => '04-00-97', 'hora' => '21:01:31'];
}
