<?php

use App\Application\Strategies\CreateMethodVersion;
use App\Application\Strategies\GenerateSignal;
use App\Application\Strategies\GenerateSignalsForDate;
use App\Application\Strategies\GenerationBlocked;
use App\Domain\Strategies\MethodCode;
use App\Infrastructure\Persistence\Eloquent\Models\Draw;
use App\Infrastructure\Persistence\Eloquent\Models\Lottery;
use App\Infrastructure\Persistence\Eloquent\Models\LotterySchedule;
use App\Infrastructure\Persistence\Eloquent\Models\Method;
use App\Infrastructure\Persistence\Eloquent\Models\MethodVersion;
use App\Infrastructure\Persistence\Eloquent\Models\Signal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\LotterySeeder;
use Database\Seeders\MethodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 5)->startOfDay());
    $this->seed([LotterySeeder::class, MethodSeeder::class]);
});

function methodFixture(string $code, string $date = '2026-09-04'): Draw
{
    $version = Method::query()->where('code', $code)->firstOrFail()->versions()->firstOrFail();
    $sourceDate = $version->source_definition['relation'] === 'same_day' ? $date : CarbonImmutable::parse($date)->subDay()->toDateString();
    LotterySchedule::query()->firstOrCreate(['lottery_id' => $version->target_lottery_id, 'weekday' => CarbonImmutable::parse($date)->isoWeekday(), 'draw_time_local' => '22:00:00', 'effective_from' => '2020-01-01'], ['is_active' => true]);

    return Draw::factory()->create(['lottery_id' => $version->source_definition['lottery_id'], 'draw_date_local' => $sourceDate, 'scheduled_at_utc' => $sourceDate.' 18:00:00', 'drawn_at_utc' => $sourceDate.' 18:00:00', 'received_at' => $sourceDate.' 18:01:00', 'confirmed_at' => $sourceDate.' 18:01:00', 'p1' => '27', 'p2' => '97', 'p3' => '64']);
}

it('implements each specified method with exact source, position and destination', function (string $code, int $target, int $source, string $expected, string $relation) {
    $draw = methodFixture($code);
    $signal = app(GenerateSignal::class)(MethodCode::from($code), '2026-09-04')['signal'];
    expect($signal->recommended_number->value())->toBe($expected)
        ->and($signal->targetLottery->external_id)->toBe($target)
        ->and($signal->target_draw_date_local->toDateString())->toBe('2026-09-04')
        ->and($signal->sources()->firstOrFail()->draw_id)->toBe($draw->id)
        ->and($draw->lottery->external_id)->toBe($source)
        ->and($signal->methodVersion->source_definition['relation'])->toBe($relation);
})->with([
    ['P01', 4, 18, '70', 'previous_day'], ['P02', 5, 15, '07', 'previous_day'], ['P03', 6, 6, '37', 'previous_day'], ['P04', 12, 5, '65', 'previous_day'], ['P05', 13, 20, '07', 'previous_day'],
    ['P06', 15, 29, '66', 'previous_day'], ['P07', 18, 5, '74', 'previous_day'], ['P08', 20, 21, '46', 'previous_day'], ['P09', 21, 5, '91', 'previous_day'], ['P10', 29, 20, '22', 'same_day'],
    ['A01', 4, 12, '37', 'previous_day'], ['A02', 5, 29, '08', 'same_day'], ['A03', 5, 20, '97', 'same_day'], ['A04', 6, 13, '91', 'previous_day'], ['A05', 12, 29, '86', 'previous_day'],
    ['A06', 12, 6, '30', 'previous_day'], ['A07', 18, 13, '91', 'previous_day'], ['A08', 18, 4, '37', 'previous_day'], ['A09', 18, 20, '67', 'same_day'], ['A10', 20, 15, '70', 'previous_day'],
]);

it('seeds exactly twenty methods and versions idempotently', function () {
    $this->seed(MethodSeeder::class);
    expect(Method::count())->toBe(20)->and(MethodVersion::count())->toBe(20)
        ->and(Method::where('category', 'primary')->count())->toBe(10)->and(Method::where('category', 'alternative')->count())->toBe(10)
        ->and(MethodVersion::where('version', 1)->count())->toBe(20);
});

it('fails explicitly if a required lottery is missing without partial inserts', function () {
    MethodVersion::query()->delete();
    Method::query()->delete();
    Lottery::where('external_id', 29)->delete();
    expect(fn () => $this->seed(MethodSeeder::class))->toThrow(LogicException::class);
    expect(Method::count())->toBe(0);
});

it('never substitutes a missing previous day with another draw', function (string $day) {
    $draw = methodFixture('P02');
    $draw->update(['draw_date_local' => $day]);
    expect(fn () => app(GenerateSignal::class)(MethodCode::P02, '2026-09-04'))->toThrow(GenerationBlocked::class, 'source_missing');
    expect(Signal::count())->toBe(0);
})->with(['2026-09-02', '2026-09-05']);

it('blocks same day sources without known timing or with a later source', function (string $code, string $scenario) {
    $draw = methodFixture($code);
    if ($scenario === 'no_schedule') {
        LotterySchedule::query()->delete();
    }
    if ($scenario === 'no_time') {
        $draw->update(['drawn_at_utc' => null]);
    }
    if ($scenario === 'late') {
        $draw->update(['drawn_at_utc' => '2026-09-05 03:00:00']);
    }
    expect(fn () => app(GenerateSignal::class)(MethodCode::from($code), '2026-09-04'))->toThrow(GenerationBlocked::class);
    expect(Signal::count())->toBe(0);
})->with(['P10', 'A02', 'A09'])->with(['no_schedule', 'no_time', 'late']);

it('blocks results received after the historical cutoff and future corrections', function (string $field) {
    $draw = methodFixture('P02');
    $draw->update([$field => '2026-09-05 03:00:00']);
    expect(fn () => app(GenerateSignal::class)(MethodCode::P02, '2026-09-04'))->toThrow(GenerationBlocked::class);
})->with(['received_at', 'confirmed_at', 'corrected_at']);

it('never uses a source that has not yet occurred for a future target', function () {
    methodFixture('P10');
    $this->travelTo(now()->setDate(2026, 9, 4)->startOfDay());
    expect(fn () => app(GenerateSignal::class)(MethodCode::P10, '2026-09-04'))->toThrow(GenerationBlocked::class);
});

it('preserves original snapshots after corrections and refuses mutation', function () {
    $draw = methodFixture('P02');
    $signal = app(GenerateSignal::class)(MethodCode::P02, '2026-09-04')['signal'];
    $draw->update(['p2' => '01', 'corrected_at' => now()]);
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/signals/'.$signal->id)->assertOk()->assertJsonPath('data.recommended_number', '07')->assertJsonPath('data.sources.0.p2', '97')->assertJsonPath('data.generated_at', now('UTC')->toIso8601String())->assertJsonMissingPath('data.sources.0.raw_payload');
    expect($signal->fresh()->calculation_snapshot)->toMatchArray(['method_code' => 'P02', 'version' => 1, 'source_draw_id' => $draw->id, 'result' => '07', 'operator' => 'add_constant_mod_100'])
        ->and($signal->calculation_snapshot['source_values']['p2'])->toBe('97');
    expect(fn () => $signal->update(['recommended_number' => '99']))->toThrow(LogicException::class);
    expect(fn () => $signal->sources()->firstOrFail()->update(['draw_id' => 999]))->toThrow(LogicException::class);
});

it('keeps used versions immutable and selects a new version only from its effective date', function () {
    methodFixture('P02');
    $first = app(GenerateSignal::class)(MethodCode::P02, '2026-09-04')['signal'];
    $v1 = $first->methodVersion;
    expect(fn () => $v1->update(['operator_definition' => ['type' => 'identity', 'first' => 'P2']]))->toThrow(LogicException::class);
    $v1->refresh();
    $v2 = app(CreateMethodVersion::class)($v1->method, [
        'target_lottery_id' => $v1->target_lottery_id,
        'source_definition' => $v1->source_definition,
        'operator_definition' => ['type' => 'identity', 'first' => 'P2'],
        'explanation_template' => 'P2', 'valid_from' => '2026-09-05', 'is_active' => true,
    ]);
    methodFixture('P02', '2026-09-05');
    $this->travelTo(now()->addDays(2));
    $next = app(GenerateSignal::class)(MethodCode::P02, '2026-09-05')['signal'];
    expect($first->fresh()->method_version_id)->toBe($v1->id)->and($next->method_version_id)->toBe($v2->id)->and($next->recommended_number->value())->toBe('97');
    expect(app(GenerateSignal::class)(MethodCode::P02, '2026-09-04')['signal']->id)->toBe($first->id);
    $this->seed(MethodSeeder::class);
    expect($v1->fresh()->operator_definition['type'])->toBe('add_constant_mod_100');
});

it('generates idempotently through application, batch, HTTP and CLI and supports dry run', function () {
    methodFixture('P02');
    Sanctum::actingAs(User::factory()->create());
    $this->artisan('signals:generate', ['--date' => '2026-09-04', '--method' => 'P02', '--dry-run' => true])->expectsOutputToContain('97 + 10 mod 100 = 07')->assertSuccessful();
    expect(Signal::count())->toBe(0);
    $first = app(GenerateSignal::class)(MethodCode::P02, '2026-09-04');
    expect(app(GenerateSignal::class)(MethodCode::P02, '2026-09-04')['outcome'])->toBe('already_exists');
    foreach (range(1, 2) as $i) {
        expect(app(GenerateSignalsForDate::class)('2026-09-04', ['P02'])['already_exists'])->toBe(1);
        $this->postJson('/api/v1/signals/generate', ['date' => '2026-09-04', 'method_codes' => ['P02']])->assertOk()->assertJsonPath('data.already_exists', 1);
        $this->artisan('signals:generate', ['--date' => '2026-09-04', '--method' => 'P02'])->assertSuccessful();
    }
    expect(Signal::count())->toBe(1)->and($first['signal']->sources()->count())->toBe(1);
    $this->getJson('/api/v1/methods')->assertOk()->assertJsonCount(20, 'data');
    $this->getJson('/api/v1/methods/'.Method::firstOrFail()->id)->assertOk();
    $this->getJson('/api/v1/signals?date=2026-09-04')->assertOk()->assertJsonCount(1, 'data');
});

it('validates HTTP dates, codes and forbids executable configuration', function () {
    Sanctum::actingAs(User::factory()->create());
    $this->postJson('/api/v1/signals/generate', ['date' => '2026-02-30'])->assertUnprocessable();
    $this->postJson('/api/v1/signals/generate', ['date' => '2026-09-04', 'method_codes' => ['X01']])->assertUnprocessable();
    $this->postJson('/api/v1/signals/generate', ['date' => '2026-09-04', 'formula' => 'php'])->assertUnprocessable();
    $this->postJson('/api/v1/signals/generate', ['date' => '2026-09-04'])->assertOk()->assertJsonPath('data.missing_source', 20);
});

it('requires authentication', function (string $path) {
    $this->getJson('/api/v1/'.$path)->assertUnauthorized();
})->with(['methods', 'methods/1', 'signals', 'signals/1']);
it('requires authentication to generate', function () {
    $this->postJson('/api/v1/signals/generate', ['date' => '2026-09-04'])->assertUnauthorized();
});

it('shows the exact target result and all matches without changing the signal snapshot', function () {
    methodFixture('P02');
    $signal = app(GenerateSignal::class)(MethodCode::P02, '2026-09-04')['signal'];
    $snapshot = $signal->refresh()->calculation_snapshot;
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/signals/'.$signal->id)->assertOk()->assertJsonPath('data.observed_result', null);
    $target = Draw::factory()->create(['lottery_id' => $signal->target_lottery_id, 'draw_date_local' => '2026-09-04', 'p1' => '14', 'p2' => '07', 'p3' => '07']);
    $this->getJson('/api/v1/signals?date=2026-09-04')->assertOk()->assertJsonPath('data.0.observed_result.draw_id', $target->id)->assertJsonPath('data.0.observed_result.matching_positions', ['P2', 'P3']);
    $target->update(['p2' => '31', 'p3' => '32', 'status' => 'corrected']);
    $this->getJson('/api/v1/signals/'.$signal->id)->assertOk()->assertJsonPath('data.observed_result.matching_positions', []);
    expect($signal->fresh()->calculation_snapshot)->toBe($snapshot);
});
