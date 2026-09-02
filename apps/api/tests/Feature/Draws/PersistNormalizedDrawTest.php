<?php

use App\Application\Draws\Data\NormalizedDrawData;
use App\Application\Draws\Normalization\NormalizedPayloadFailure;
use App\Application\Draws\Persistence\PersistDrawQuarantine;
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
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->lottery = Lottery::factory()->create(['external_id' => 4]);
    $this->run = SyncRun::factory()->for($this->lottery)->create([
        'items_inserted' => 0,
        'items_updated' => 0,
        'items_unchanged' => 0,
        'items_quarantined' => 0,
    ]);
    $sanitizer = new ProviderSecretSanitizer('reflected-provider-secret');
    $this->persist = new PersistNormalizedDraw(new PersistDrawQuarantine($sanitizer), $sanitizer);
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
    ($this->persist)(new NormalizedPayloadFailure('invalid_prizes', 'Invalid payload.', [
        'token' => 'reflected-provider-secret',
        'url' => 'https://api.elboletoganador.com/api/sorteos/reflected-provider-secret/4',
    ], 4), $this->run);

    $payload = DrawQuarantine::query()->sole()->raw_payload;
    expect($payload['token'])->toBe('[REDACTED]')
        ->and($payload['url'])->toContain('/api/sorteos/[REDACTED]/4')
        ->and(json_encode($payload, JSON_THROW_ON_ERROR))->not->toContain('reflected-provider-secret');
});

it('recovers a duplicate identity as unchanged when the normalized payload is retried', function (): void {
    ($this->persist)(normalizedDraw(), $this->run);
    $result = ($this->persist)(normalizedDraw(), $this->run);

    expect($result->status)->toBe('unchanged')
        ->and(Draw::query()->count())->toBe(1);
});

it('quarantines two conflicting identities without changing either draw', function (): void {
    $external = Draw::factory()->for($this->lottery)->create([
        'provider' => 'elboletoganador',
        'external_draw_id' => '227821',
        'scheduled_at_utc' => '2026-09-01 00:00:00',
        'source_hash' => hash('sha256', 'external'),
    ]);
    $scheduled = Draw::factory()->for($this->lottery)->create([
        'provider' => 'elboletoganador',
        'external_draw_id' => 'other-external-id',
        'scheduled_at_utc' => '2026-09-01 01:01:31',
        'source_hash' => hash('sha256', 'scheduled'),
    ]);

    $result = ($this->persist)(normalizedDraw(), $this->run);

    $this->run->refresh();
    expect($result->isConflict())->toBeTrue()
        ->and(Draw::query()->findOrFail($external->id)->source_hash)->toBe(hash('sha256', 'external'))
        ->and(Draw::query()->findOrFail($scheduled->id)->source_hash)->toBe(hash('sha256', 'scheduled'))
        ->and(SyncError::query()->count())->toBe(1)
        ->and(DrawQuarantine::query()->sole()->error_code)->toBe('identity_conflict')
        ->and($this->run->items_quarantined)->toBe(1);
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
