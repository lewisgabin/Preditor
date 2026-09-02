<?php

use App\Application\Draws\Data\NormalizedDrawData;
use App\Application\Draws\Events\DrawConfirmed;
use App\Application\Draws\Events\DrawCorrected;
use App\Application\Draws\Events\DrawQuarantined;
use App\Application\Draws\Normalization\NormalizedPayloadFailure;
use App\Application\Draws\Persistence\PersistNormalizedDraw;
use App\Domain\Draws\Enums\DrawStatus;
use App\Domain\Draws\ValueObjects\LotteryNumber;
use App\Infrastructure\Draws\Security\ProviderSecretSanitizer;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\DrawCorrection;
use App\Infrastructure\Persistence\Eloquent\Models\DrawQuarantine;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\SyncError;
use App\Infrastructure\Persistence\Eloquent\Models\SyncRun;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Database\QueryException;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(DatabaseMigrations::class);

beforeEach(function (): void {
    $this->lottery = Lottery::factory()->create(['external_id' => 4]);
    $this->run = SyncRun::factory()->for($this->lottery)->create([
        'items_inserted' => 0,
        'items_updated' => 0,
        'items_unchanged' => 0,
        'items_quarantined' => 0,
    ]);
    config(['lottery-api.key' => 'reflected-provider-secret']);
    $this->app->forgetInstance(ProviderSecretSanitizer::class);
    $this->persist = $this->app->make(PersistNormalizedDraw::class);
});

it('inserts a confirmed normalized draw and increments its run in one transaction', function (): void {
    $result = ($this->persist)(normalizedDraw(), $this->run);

    expect($result->status)->toBe('inserted');
    $this->run->refresh();
    expect(Draw::query()->count())->toBe(1)
        ->and(Draw::query()->sole()->status)->toBe(DrawStatus::Confirmed)
        ->and($this->run->items_inserted)->toBe(1);
});

it('leaves ten repeated payloads unchanged after the first insert', function (): void {
    ($this->persist)(normalizedDraw(), $this->run);

    foreach (range(1, 10) as $_) {
        expect(($this->persist)(normalizedDraw(), $this->run)->status)->toBe('unchanged');
    }

    $this->run->refresh();
    expect(Draw::query()->count())->toBe(1)
        ->and(DrawCorrection::query()->count())->toBe(0)
        ->and($this->run->items_inserted)->toBe(1)
        ->and($this->run->items_unchanged)->toBe(10);
});

it('appends a correction before updating the draw hash and corrected status', function (): void {
    ($this->persist)(normalizedDraw(), $this->run);
    $result = ($this->persist)(normalizedDraw(['p1' => '04', 'sourceHash' => hash('sha256', 'corrected')]), $this->run);

    $draw = Draw::query()->sole();
    $correction = DrawCorrection::query()->sole();
    $this->run->refresh();
    expect($result->status)->toBe('updated')
        ->and($draw->status)->toBe(DrawStatus::Corrected)
        ->and($draw->p1->value())->toBe('04')
        ->and($draw->source_hash)->toBe(hash('sha256', 'corrected'))
        ->and($draw->corrected_at)->not->toBeNull()
        ->and($correction->before_hash)->toBe(hash('sha256', 'original'))
        ->and($correction->after_hash)->toBe(hash('sha256', 'corrected'))
        ->and($this->run->items_updated)->toBe(1);
});

it('quarantines malformed values and unknown lotteries without creating draws', function (NormalizedPayloadFailure $failure): void {
    $result = ($this->persist)($failure, $this->run);

    $this->run->refresh();
    expect($result->status)->toBe('quarantined')
        ->and(Draw::query()->count())->toBe(0)
        ->and(DrawQuarantine::query()->count())->toBe(1)
        ->and($this->run->items_quarantined)->toBe(1);
})->with([
    'two prizes' => new NormalizedPayloadFailure('invalid_prizes', 'Exactly three prizes are required.', ['premios' => '04-00'], 4),
    'number 105' => new NormalizedPayloadFailure('invalid_prizes', 'Lottery prizes are invalid.', ['premios' => '04-00-105'], 4),
    'unknown lottery' => new NormalizedPayloadFailure('unknown_lottery', 'Lottery does not exist.', ['loteria_id' => 999], 999),
]);

it('sanitizes reflected provider secrets before quarantining', function (): void {
    $secret = 'arbitrary-secret-value';
    config(['lottery-api.key' => $secret]);
    $this->app->forgetInstance(ProviderSecretSanitizer::class);
    $persist = $this->app->make(PersistNormalizedDraw::class);

    ($persist)(new NormalizedPayloadFailure('invalid_prizes', 'Invalid payload.', [
        'token' => $secret,
        'url' => 'https://api.elboletoganador.com/api/sorteos/'.$secret.'/4',
    ], 4), $this->run);

    $payload = DrawQuarantine::query()->sole()->raw_payload;
    expect($payload['token'])->toBe('[REDACTED]')
        ->and($payload['url'])->toContain('/api/sorteos/[REDACTED]/4')
        ->and(json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain($secret);
});

it('dispatches draw events only after the transaction commits', function (): void {
    Event::fake([DrawConfirmed::class, DrawCorrected::class, DrawQuarantined::class]);

    ($this->persist)(normalizedDraw(), $this->run);
    Event::assertDispatched(DrawConfirmed::class);

    ($this->persist)(normalizedDraw(['p1' => '04', 'sourceHash' => hash('sha256', 'corrected')]), $this->run);
    Event::assertDispatched(DrawCorrected::class);

    ($this->persist)(new NormalizedPayloadFailure('invalid_prizes', 'Invalid payload.', ['premios' => '04-00'], 4), $this->run);
    Event::assertDispatched(DrawQuarantined::class);
});

it('suppresses draw events when an outer transaction rolls back', function (): void {
    Event::fake([DrawConfirmed::class]);

    try {
        DB::transaction(function (): void {
            ($this->persist)(normalizedDraw(), $this->run);

            throw new RuntimeException('rollback test transaction');
        });
    } catch (RuntimeException) {
        // The rollback is the behavior under test.
    }

    Event::assertNotDispatched(DrawConfirmed::class);
    expect(Draw::query()->count())->toBe(0);
});

it('recovers a forced duplicate-key race as unchanged without appending a correction', function (): void {
    $data = normalizedDraw();
    config(['database.connections.draw-race' => config('database.connections.mysql')]);
    DB::purge('draw-race');
    $originalConnectionDispatcher = DB::connection()->getEventDispatcher();
    $originalModelDispatcher = Draw::getEventDispatcher();
    $testConnectionDispatcher = new Dispatcher($this->app);
    $testModelDispatcher = new Dispatcher($this->app);
    DB::connection()->setEventDispatcher($testConnectionDispatcher);
    Draw::setEventDispatcher($testModelDispatcher);

    $insertedByRace = false;
    $testConnectionDispatcher->listen(TransactionRolledBack::class, function () use (&$insertedByRace, $data): void {
        if ($insertedByRace) {
            return;
        }

        $insertedByRace = true;
        DB::connection('draw-race')->table('draws')->insert([
            'lottery_id' => $this->lottery->id,
            'provider' => $data->provider,
            'external_draw_id' => $data->externalDrawId,
            'draw_date_local' => $data->drawDateLocal->format('Y-m-d'),
            'scheduled_at_utc' => $data->scheduledAtUtc?->format('Y-m-d H:i:s.u'),
            'drawn_at_utc' => $data->drawnAtUtc?->format('Y-m-d H:i:s.u'),
            'p1' => $data->p1->value(),
            'p2' => $data->p2->value(),
            'p3' => $data->p3->value(),
            'status' => DrawStatus::Confirmed->value,
            'source_hash' => $data->sourceHash,
            'raw_payload' => json_encode($data->rawPayload, JSON_THROW_ON_ERROR),
            'received_at' => $data->receivedAt->format('Y-m-d H:i:s.u'),
            'confirmed_at' => now()->format('Y-m-d H:i:s.u'),
            'corrected_at' => null,
            'created_at' => now()->format('Y-m-d H:i:s'),
            'updated_at' => now()->format('Y-m-d H:i:s'),
        ]);
    });

    Draw::creating(function (): void {
        throw new QueryException('mysql', 'insert into draws', [], new PDOException('Duplicate entry', 23000));
    });

    try {
        $result = ($this->persist)($data, $this->run);
    } finally {
        Draw::setEventDispatcher($originalModelDispatcher);
        DB::connection()->setEventDispatcher($originalConnectionDispatcher);
        DB::purge('draw-race');
    }

    expect($result->status)->toBe('unchanged')
        ->and(Draw::query()->count())->toBe(1)
        ->and(DrawCorrection::query()->count())->toBe(0)
        ->and($insertedByRace)->toBeTrue();
});

it('quarantines conflicting identities recovered after a duplicate-key race', function (): void {
    $data = normalizedDraw();
    config(['database.connections.draw-race' => config('database.connections.mysql')]);
    DB::purge('draw-race');
    $originalConnectionDispatcher = DB::connection()->getEventDispatcher();
    $originalModelDispatcher = Draw::getEventDispatcher();
    $testConnectionDispatcher = new Dispatcher($this->app);
    $testModelDispatcher = new Dispatcher($this->app);
    DB::connection()->setEventDispatcher($testConnectionDispatcher);
    Draw::setEventDispatcher($testModelDispatcher);

    $testConnectionDispatcher->listen(TransactionRolledBack::class, function () use ($data): void {
        DB::connection('draw-race')->table('draws')->insert([
            raceDrawAttributes($data, $this->lottery->id, '227821', '2026-09-01 00:00:00.000000', hash('sha256', 'external')),
            raceDrawAttributes($data, $this->lottery->id, 'other-external-id', '2026-09-01 01:01:31.000000', hash('sha256', 'scheduled')),
        ]);
    });
    Draw::creating(function (): void {
        throw new QueryException('mysql', 'insert into draws', [], new PDOException('Duplicate entry', 23000));
    });

    try {
        $result = ($this->persist)($data, $this->run);
    } finally {
        Draw::setEventDispatcher($originalModelDispatcher);
        DB::connection()->setEventDispatcher($originalConnectionDispatcher);
        DB::purge('draw-race');
    }

    $this->run->refresh();
    expect($result->isConflict())->toBeTrue()
        ->and(Draw::query()->count())->toBe(2)
        ->and(DrawCorrection::query()->count())->toBe(0)
        ->and(SyncError::query()->count())->toBe(1)
        ->and(DrawQuarantine::query()->sole()->error_code)->toBe('identity_conflict')
        ->and($this->run->items_quarantined)->toBe(1);
});

it('uses the external identity without falling back to an unrelated scheduled row', function (): void {
    $external = Draw::factory()->for($this->lottery)->create([
        'provider' => 'elboletoganador',
        'external_draw_id' => '227821',
        'scheduled_at_utc' => '2026-09-01 00:00:00',
        'source_hash' => hash('sha256', 'original'),
    ]);
    $scheduled = Draw::factory()->for($this->lottery)->create([
        'provider' => 'elboletoganador',
        'external_draw_id' => 'other-external-id',
        'scheduled_at_utc' => '2026-09-01 01:01:31',
        'source_hash' => hash('sha256', 'scheduled'),
    ]);

    $result = ($this->persist)(normalizedDraw(), $this->run);

    $this->run->refresh();
    expect($result->status)->toBe('unchanged')
        ->and(Draw::query()->findOrFail($external->id)->source_hash)->toBe(hash('sha256', 'original'))
        ->and(Draw::query()->findOrFail($scheduled->id)->source_hash)->toBe(hash('sha256', 'scheduled'))
        ->and(DrawQuarantine::query()->count())->toBe(0)
        ->and($this->run->items_quarantined)->toBe(0);
});

/** @param array<string, string> $overrides */
function normalizedDraw(array $overrides = []): NormalizedDrawData
{
    $values = array_replace([
        'p1' => '50',
        'p2' => '32',
        'p3' => '77',
        'sourceHash' => hash('sha256', 'original'),
    ], $overrides);

    return new NormalizedDrawData(
        lotteryExternalId: 4,
        provider: 'elboletoganador',
        externalDrawId: '227821',
        drawDateLocal: new DateTimeImmutable('2026-08-31'),
        scheduledAtUtc: new DateTimeImmutable('2026-09-01T01:01:31Z'),
        drawnAtUtc: new DateTimeImmutable('2026-09-01T01:01:31Z'),
        p1: new LotteryNumber($values['p1']),
        p2: new LotteryNumber($values['p2']),
        p3: new LotteryNumber($values['p3']),
        status: DrawStatus::Confirmed,
        sourceHash: $values['sourceHash'],
        rawPayload: ['id' => 227821, 'premios' => $values['p1'].'-'.$values['p2'].'-'.$values['p3']],
        receivedAt: new DateTimeImmutable('2026-09-02T12:00:00Z'),
    );
}

/** @return array<string, mixed> */
function raceDrawAttributes(NormalizedDrawData $data, int $lotteryId, string $externalDrawId, string $scheduledAtUtc, string $sourceHash): array
{
    return [
        'lottery_id' => $lotteryId,
        'provider' => $data->provider,
        'external_draw_id' => $externalDrawId,
        'draw_date_local' => $data->drawDateLocal->format('Y-m-d'),
        'scheduled_at_utc' => $scheduledAtUtc,
        'drawn_at_utc' => $data->drawnAtUtc?->format('Y-m-d H:i:s.u'),
        'p1' => $data->p1->value(),
        'p2' => $data->p2->value(),
        'p3' => $data->p3->value(),
        'status' => DrawStatus::Confirmed->value,
        'source_hash' => $sourceHash,
        'raw_payload' => json_encode($data->rawPayload, JSON_THROW_ON_ERROR),
        'received_at' => $data->receivedAt->format('Y-m-d H:i:s.u'),
        'confirmed_at' => now()->format('Y-m-d H:i:s.u'),
        'corrected_at' => null,
        'created_at' => now()->format('Y-m-d H:i:s'),
        'updated_at' => now()->format('Y-m-d H:i:s'),
    ];
}
