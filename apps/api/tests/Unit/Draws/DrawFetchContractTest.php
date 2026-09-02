<?php

use App\Application\Draws\Contracts\LotteryDrawProvider;
use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Data\DrawFetchResult;
use App\Application\Draws\Data\DrawProviderCapabilities;
use App\Domain\Draws\Enums\SyncTrigger;

it('builds a request for the current draw', function (): void {
    $request = new DrawFetchRequest('fixture', 7, SyncTrigger::Scheduled);

    expect($request->provider)->toBe('fixture')
        ->and($request->lotteryExternalId)->toBe(7)
        ->and($request->trigger)->toBe(SyncTrigger::Scheduled)
        ->and($request->date)->toBeNull()
        ->and($request->rangeStart)->toBeNull()
        ->and($request->rangeEnd)->toBeNull();
});

it('builds a request for a specific date', function (): void {
    $date = new DateTimeImmutable('2026-09-02');

    $request = new DrawFetchRequest('fixture', 7, SyncTrigger::Manual, date: $date);

    expect($request->date)->toBe($date)
        ->and($request->rangeStart)->toBeNull()
        ->and($request->rangeEnd)->toBeNull();
});

it('builds a request for a date range', function (): void {
    $start = new DateTimeImmutable('2026-09-01');
    $end = new DateTimeImmutable('2026-09-02');

    $request = new DrawFetchRequest('fixture', 7, SyncTrigger::Historical, rangeStart: $start, rangeEnd: $end);

    expect($request->date)->toBeNull()
        ->and($request->rangeStart)->toBe($start)
        ->and($request->rangeEnd)->toBe($end);
});

it('rejects a request that combines date and range', function (): void {
    new DrawFetchRequest(
        'fixture',
        7,
        SyncTrigger::Manual,
        date: new DateTimeImmutable('2026-09-02'),
        rangeStart: new DateTimeImmutable('2026-09-01'),
        rangeEnd: new DateTimeImmutable('2026-09-02'),
    );
})->throws(InvalidArgumentException::class);

it('rejects an inverted range', function (): void {
    new DrawFetchRequest(
        'fixture',
        7,
        SyncTrigger::Historical,
        rangeStart: new DateTimeImmutable('2026-09-03'),
        rangeEnd: new DateTimeImmutable('2026-09-02'),
    );
})->throws(InvalidArgumentException::class);

it('rejects a non-positive external lottery id', function (int $lotteryExternalId): void {
    new DrawFetchRequest('fixture', $lotteryExternalId, SyncTrigger::Manual);
})->with([0, -1])->throws(InvalidArgumentException::class);

it('exposes explicit fetch result states', function (): void {
    expect(DrawFetchResult::AVAILABLE)->toBe('available')
        ->and(DrawFetchResult::NOT_AVAILABLE)->toBe('not_available')
        ->and(DrawFetchResult::FAILURE)->toBe('failure');
});

it('builds an available result with sanitized array payloads', function (): void {
    $result = DrawFetchResult::available([['draw_id' => 'fixture-1', 'results' => ['00', '01', '09']]]);

    expect($result->status)->toBe(DrawFetchResult::AVAILABLE)
        ->and($result->payloads)->toBe([['draw_id' => 'fixture-1', 'results' => ['00', '01', '09']]])
        ->and($result->failureReason)->toBeNull()
        ->and($result->httpStatus)->toBeNull()
        ->and($result->safeContext)->toBe([]);
});

it('builds a not available result without payloads or failure details', function (): void {
    $result = DrawFetchResult::notAvailable();

    expect($result->status)->toBe(DrawFetchResult::NOT_AVAILABLE)
        ->and($result->payloads)->toBe([])
        ->and($result->failureReason)->toBeNull()
        ->and($result->httpStatus)->toBeNull()
        ->and($result->safeContext)->toBe([]);
});

it('builds a failure result with an optional status and sanitized context', function (): void {
    $result = DrawFetchResult::failure('The provider timed out.', 504, [
        'provider_code' => 'gateway_timeout',
        'api_token' => 'must-not-leak',
    ]);

    expect($result->status)->toBe(DrawFetchResult::FAILURE)
        ->and($result->payloads)->toBe([])
        ->and($result->failureReason)->toBe('The provider timed out.')
        ->and($result->httpStatus)->toBe(504)
        ->and($result->safeContext)->toBe([
            'provider_code' => 'gateway_timeout',
            'api_token' => '[redacted]',
        ]);
});

it('rejects invalid payloads and state combinations', function (string $status, array $payloads, ?string $reason, ?int $httpStatus, array $safeContext): void {
    new DrawFetchResult($status, $payloads, $reason, $httpStatus, $safeContext);
})->with([
    'available without payloads' => [DrawFetchResult::AVAILABLE, [], null, null, []],
    'available with failure details' => [DrawFetchResult::AVAILABLE, [['draw_id' => 'fixture-1']], 'Unexpected failure', null, []],
    'not available with payloads' => [DrawFetchResult::NOT_AVAILABLE, [['draw_id' => 'fixture-1']], null, null, []],
    'not available with failure details' => [DrawFetchResult::NOT_AVAILABLE, [], 'Unexpected failure', 500, []],
    'failure without reason' => [DrawFetchResult::FAILURE, [], null, 500, []],
    'failure with payloads' => [DrawFetchResult::FAILURE, [['draw_id' => 'fixture-1']], 'Unexpected failure', 500, []],
    'non array payload element' => [DrawFetchResult::AVAILABLE, ['not-an-array'], null, null, []],
])->throws(InvalidArgumentException::class);

it('declares provider capabilities for current, date and range fetches', function (): void {
    $capabilities = new DrawProviderCapabilities(current: true, date: false, range: true);

    expect($capabilities->current)->toBeTrue()
        ->and($capabilities->date)->toBeFalse()
        ->and($capabilities->range)->toBeTrue();
});

it('defines a provider contract independent from HTTP and Eloquent types', function (): void {
    $capabilities = new ReflectionMethod(LotteryDrawProvider::class, 'capabilities');
    $fetch = new ReflectionMethod(LotteryDrawProvider::class, 'fetch');

    expect($capabilities->getReturnType()?->getName())->toBe(DrawProviderCapabilities::class)
        ->and($fetch->getReturnType()?->getName())->toBe(DrawFetchResult::class)
        ->and($fetch->getParameters()[0]->getType()?->getName())->toBe(DrawFetchRequest::class);
});
