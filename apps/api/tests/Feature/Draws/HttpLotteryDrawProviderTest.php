<?php

use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Data\DrawFetchResult;
use App\Domain\Draws\Enums\SyncTrigger;
use App\Infrastructure\Draws\Providers\HttpLotteryDrawProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->apiKey = 'test-provider-key';
    $this->provider = new HttpLotteryDrawProvider('https://api.elboletoganador.com', $this->apiKey, 3, 2);
});

it('fetches a current draw from the fixed provider endpoint without exposing its key in result details', function (): void {
    Http::fake(['https://api.elboletoganador.com/*' => Http::response(['fecha_sorteo' => '2099-01-01', 'premios' => '12-34-56'])]);

    $result = $this->provider->fetch(httpDrawRequest());

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'GET'
            && $request->url() === 'https://api.elboletoganador.com/api/sorteos/'.$this->apiKey.'/4';
    });
    Http::assertSentCount(1);

    expect($result->status)->toBe(DrawFetchResult::AVAILABLE)
        ->and($result->payloads)->toBe([['fecha_sorteo' => '2099-01-01', 'premios' => '12-34-56']])
        ->and(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain($this->apiKey);
});

it('treats null and an empty object response as pending', function (mixed $payload): void {
    Http::fake(['https://api.elboletoganador.com/*' => Http::response($payload)]);

    $result = $this->provider->fetch(httpDrawRequest());

    expect($result->status)->toBe(DrawFetchResult::NOT_AVAILABLE);
})->with([[null], [[]]]);

it('keeps a non-empty response list for the normalizer to reject later', function (): void {
    $payload = [['fecha_sorteo' => '2099-01-01', 'premios' => '12-34-56']];
    Http::fake(['https://api.elboletoganador.com/*' => Http::response($payload)]);

    $result = $this->provider->fetch(httpDrawRequest());

    expect($result->status)->toBe(DrawFetchResult::AVAILABLE)->and($result->payloads)->toBe([$payload]);
});

it('treats an object from a previous Santo Domingo day as pending', function (): void {
    Http::fake(['https://api.elboletoganador.com/*' => Http::response(['fecha_sorteo' => '2000-01-01', 'premios' => '12-34-56'])]);

    expect($this->provider->fetch(httpDrawRequest())->status)->toBe(DrawFetchResult::NOT_AVAILABLE);
});

it('rejects historical, range, and reconciliation requests before making an HTTP call', function (DrawFetchRequest $request): void {
    Http::fake();

    $result = $this->provider->fetch($request);

    Http::assertNothingSent();
    expect($result->status)->toBe(DrawFetchResult::FAILURE)
        ->and($result->safeContext)->toBe(['category' => 'unsupported_request']);
})->with([
    'historical date' => [httpDrawRequest(trigger: SyncTrigger::Historical, date: new DateTimeImmutable('2026-09-01'))],
    'date request' => [httpDrawRequest(date: new DateTimeImmutable('2026-09-02'))],
    'range request' => [httpDrawRequest(rangeStart: new DateTimeImmutable('2026-08-30'), rangeEnd: new DateTimeImmutable('2026-09-02'))],
    'reconciliation request' => [httpDrawRequest(trigger: SyncTrigger::Reconciliation)],
]);

it('classifies a connection timeout without leaking its request URL', function (): void {
    Http::fake(static fn (): never => throw new ConnectionException('Connection timed out for https://api.elboletoganador.com/api/sorteos/test-provider-key/4'));

    $result = $this->provider->fetch(httpDrawRequest());

    expect($result->status)->toBe(DrawFetchResult::FAILURE)
        ->and($result->failureReason)->toBe('The lottery draw provider request timed out.')
        ->and($result->safeContext)->toBe(['category' => 'timeout'])
        ->and(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain($this->apiKey);
});

it('classifies HTTP failures without treating 404 as pending', function (int $status, string $category): void {
    Http::fake(['https://api.elboletoganador.com/*' => Http::response(['token' => $this->apiKey], $status, ['Retry-After' => $status === 429 ? '60' : ''])]);

    $result = $this->provider->fetch(httpDrawRequest());

    expect($result->status)->toBe(DrawFetchResult::FAILURE)
        ->and($result->httpStatus)->toBe($status)
        ->and($result->safeContext)->toBe($status === 429 ? ['category' => $category, 'retry_after' => '60'] : ['category' => $category])
        ->and(json_encode($result, JSON_THROW_ON_ERROR))->not->toContain($this->apiKey);
})->with([
    'bad request' => [400, 'permanent'],
    'unauthorized' => [401, 'authentication'],
    'forbidden' => [403, 'authentication'],
    'not found' => [404, 'permanent'],
    'request timeout' => [408, 'temporary'],
    'unprocessable' => [422, 'permanent'],
    'rate limited' => [429, 'rate_limited'],
    'server error' => [500, 'temporary'],
    'bad gateway' => [502, 'temporary'],
    'service unavailable' => [503, 'temporary'],
    'gateway timeout' => [504, 'temporary'],
]);

it('declares only current-draw capability', function (): void {
    expect($this->provider->capabilities()->current)->toBeTrue()
        ->and($this->provider->capabilities()->date)->toBeFalse()
        ->and($this->provider->capabilities()->range)->toBeFalse();
});

function httpDrawRequest(
    SyncTrigger $trigger = SyncTrigger::Manual,
    ?DateTimeImmutable $date = null,
    ?DateTimeImmutable $rangeStart = null,
    ?DateTimeImmutable $rangeEnd = null,
): DrawFetchRequest {
    return new DrawFetchRequest('elboletoganador', 4, $trigger, $date, $rangeStart, $rangeEnd);
}
