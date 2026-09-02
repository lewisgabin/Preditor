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
