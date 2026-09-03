<?php

use App\Application\Draws\Data\DrawFetchResult;
use App\Application\Draws\Events\DrawConfirmed;
use App\Application\Draws\Events\DrawCorrected;
use App\Application\Draws\Events\DrawQuarantined;
use App\Application\Draws\Events\DrawSyncCompleted;
use App\Domain\Draws\Enums\SyncRunStatus;
use App\Domain\Draws\Enums\SyncTrigger;
use App\Infrastructure\Draws\Jobs\SyncLotteryDrawsJob;
use App\Infrastructure\Draws\Providers\FakeLotteryDrawProvider;
use App\Infrastructure\Draws\Providers\HttpLotteryDrawProvider;
use App\Infrastructure\Draws\Providers\LotteryDrawProviderResolver;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\DrawCorrection;
use App\Infrastructure\Persistence\Eloquent\Models\DrawQuarantine;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncError;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->lottery = Lottery::factory()->create(['external_id' => 4]);
});

it('queues a current fake provider request through the existing orchestrator', function (): void {
    Queue::fake();
    commandProvider(new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::notAvailable()));

    $this->artisan('draws:sync', ['--provider' => 'fake', '--lottery' => '4'])
        ->assertSuccessful();

    $run = SyncRun::query()->sole();
    expect($run->provider)->toBe('fake')
        ->and($run->trigger)->toBe(SyncTrigger::Manual)
        ->and($run->lottery_id)->toBe($this->lottery->id)
        ->and($run->status)->toBe(SyncRunStatus::Queued);
    Queue::assertPushed(SyncLotteryDrawsJob::class, static fn (SyncLotteryDrawsJob $job): bool => $job->syncRunUuid === $run->uuid && $job->request->lotteryExternalId === 4);
});

it('allows fake historical ranges and uses the reconciliation trigger', function (): void {
    Queue::fake();
    commandProvider(new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::notAvailable()));

    $this->artisan('draws:reconcile', [
        '--provider' => 'fake', '--lottery' => '4', '--from' => '2026-08-01', '--to' => '2026-08-03',
    ])->assertSuccessful();

    $run = SyncRun::query()->sole();
    expect($run->trigger)->toBe(SyncTrigger::Reconciliation)
        ->and($run->requested_from->format('Y-m-d'))->toBe('2026-08-01')
        ->and($run->requested_to->format('Y-m-d'))->toBe('2026-08-03');
    Queue::assertPushed(SyncLotteryDrawsJob::class, static fn (SyncLotteryDrawsJob $job): bool => $job->request->rangeStart?->format('Y-m-d') === '2026-08-01');
});

it('rejects unsupported real provider scopes before creating a run or dispatching a job', function (array $parameters): void {
    Queue::fake();
    app()->instance(LotteryDrawProviderResolver::class, new LotteryDrawProviderResolver([
        'elboletoganador' => new HttpLotteryDrawProvider('https://example.test', 'command-secret'),
    ], 'elboletoganador', true));

    $this->artisan('draws:sync', $parameters)->assertFailed();

    expect(SyncRun::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'missing lottery' => ['--provider' => 'elboletoganador'],
    'date' => ['--provider' => 'elboletoganador', '--lottery' => '4', '--date' => '2026-08-01'],
    'range' => ['--provider' => 'elboletoganador', '--lottery' => '4', '--from' => '2026-08-01', '--to' => '2026-08-03'],
]);

it('rejects reconciliation for the real provider before it can make an HTTP request', function (): void {
    Queue::fake();
    app()->instance(LotteryDrawProviderResolver::class, new LotteryDrawProviderResolver([
        'elboletoganador' => new HttpLotteryDrawProvider('https://example.test', 'command-secret'),
    ], 'elboletoganador', true));

    $this->artisan('draws:reconcile', ['--provider' => 'elboletoganador', '--lottery' => '4'])
        ->assertFailed();

    expect(SyncRun::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('does not let force bypass real provider capabilities', function (): void {
    Queue::fake();
    app()->instance(LotteryDrawProviderResolver::class, new LotteryDrawProviderResolver([
        'elboletoganador' => new HttpLotteryDrawProvider('https://example.test', 'command-secret'),
    ], 'elboletoganador', false));

    $this->artisan('draws:sync', ['--provider' => 'elboletoganador', '--lottery' => '4', '--date' => '2026-08-01', '--force' => true])
        ->assertFailed();

    expect(SyncRun::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('runs dry mode without queueing or persisting draw effects', function (): void {
    Queue::fake();
    Event::fake([DrawConfirmed::class, DrawCorrected::class, DrawQuarantined::class, DrawSyncCompleted::class]);
    commandProvider(new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::available([commandPayload()])));

    $this->artisan('draws:sync', ['--provider' => 'fake', '--lottery' => '4', '--dry-run' => true])
        ->assertSuccessful();

    $run = SyncRun::query()->sole();
    expect($run->status)->toBe(SyncRunStatus::Succeeded)
        ->and($run->metadata['dry_run'])->toBeTrue()
        ->and($run->items_received)->toBe(1)
        ->and($run->items_inserted)->toBe(0)
        ->and($run->items_updated)->toBe(0)
        ->and($run->items_unchanged)->toBe(0)
        ->and($run->items_quarantined)->toBe(0)
        ->and(Draw::query()->count())->toBe(0)
        ->and(DrawCorrection::query()->count())->toBe(0)
        ->and(DrawQuarantine::query()->count())->toBe(0);
    Queue::assertNothingPushed();
    Event::assertNotDispatched(DrawConfirmed::class);
    Event::assertNotDispatched(DrawCorrected::class);
    Event::assertNotDispatched(DrawQuarantined::class);
    Event::assertNotDispatched(DrawSyncCompleted::class);
});

it('fails a dry run with a sanitized sync error for invalid payloads', function (): void {
    Queue::fake();
    commandProvider(new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::available([['id' => 1, 'token' => 'command-secret']])));

    $this->artisan('draws:sync', ['--provider' => 'fake', '--lottery' => '4', '--dry-run' => true])
        ->assertSuccessful();

    $run = SyncRun::query()->sole();
    $error = SyncError::query()->sole();
    expect($run->status)->toBe(SyncRunStatus::Failed)
        ->and($run->metadata['dry_run'])->toBeTrue()
        ->and($run->items_received)->toBe(1)
        ->and($run->items_inserted)->toBe(0)
        ->and($run->items_updated)->toBe(0)
        ->and($run->items_unchanged)->toBe(0)
        ->and($run->items_quarantined)->toBe(0)
        ->and($error->retryable)->toBeFalse()
        ->and(json_encode($error->safe_context, JSON_THROW_ON_ERROR))->not->toContain('command-secret');
    Queue::assertNothingPushed();
});

function commandProvider(FakeLotteryDrawProvider $provider): void
{
    app()->instance(LotteryDrawProviderResolver::class, new LotteryDrawProviderResolver(['fake' => $provider], 'fake', true));
}

/** @return array<string, mixed> */
function commandPayload(): array
{
    return ['id' => 227821, 'loteria_id' => 4, 'fecha_sorteo' => '2026-09-01', 'premios' => '04-00-97', 'hora' => '21:01:31'];
}
