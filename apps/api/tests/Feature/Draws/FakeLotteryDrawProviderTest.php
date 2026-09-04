<?php

use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Data\DrawFetchResult;
use App\Application\Draws\Data\NormalizedDrawData;
use App\Application\Draws\Normalization\ProviderPayloadNormalizer;
use App\Domain\Draws\Enums\SyncTrigger;
use App\Infrastructure\Draws\Providers\FakeLotteryDrawProvider;
use App\Infrastructure\Draws\Providers\LotteryDrawProviderResolver;

it('returns an injected available payload without using the network', function (): void {
    $payload = drawFixture('available');
    $provider = new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::available([$payload]));

    $result = $provider->fetch(drawRequest());

    expect($result->status)->toBe(DrawFetchResult::AVAILABLE)
        ->and($result->payloads)->toBe([$payload]);
});

it('can return the same payload repeatedly for duplicate processing', function (): void {
    $provider = new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::available([drawFixture('available')]));

    expect($provider->fetch(drawRequest()))->toEqual($provider->fetch(drawRequest()));
});

it('can inject a corrected payload sequence', function (): void {
    $responses = [drawFixture('available'), drawFixture('corrected')];
    $provider = new FakeLotteryDrawProvider(responder: static function () use (&$responses): DrawFetchResult {
        return DrawFetchResult::available([array_shift($responses)]);
    });

    expect($provider->fetch(drawRequest())->payloads)->toBe([drawFixture('available')])
        ->and($provider->fetch(drawRequest())->payloads)->toBe([drawFixture('corrected')]);
});

it('treats empty array and null scenarios as pending results', function (mixed $scenario): void {
    $provider = new FakeLotteryDrawProvider(responder: static fn (): DrawFetchResult => match ($scenario) {
        [], null => DrawFetchResult::notAvailable(),
    });

    $result = $provider->fetch(drawRequest());

    expect($result->status)->toBe(DrawFetchResult::NOT_AVAILABLE)
        ->and($result->payloads)->toBe([]);
})->with([
    'empty list' => [[]],
    'null' => [null],
]);

it('returns invalid payloads unchanged for the normalizer to quarantine later', function (string $fixture): void {
    $payload = drawFixture($fixture);
    $provider = new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::available([$payload]));

    expect($provider->fetch(drawRequest())->payloads)->toBe([$payload]);
})->with(['invalid-two-prizes', 'invalid-number', 'unknown-lottery']);

it('returns typed failure scenarios without network access', function (string $reason, ?int $httpStatus): void {
    $provider = new FakeLotteryDrawProvider(defaultResult: DrawFetchResult::failure($reason, $httpStatus, ['scenario' => 'fixture']));

    $result = $provider->fetch(drawRequest());

    expect($result->status)->toBe(DrawFetchResult::FAILURE)
        ->and($result->failureReason)->toBe($reason)
        ->and($result->httpStatus)->toBe($httpStatus)
        ->and($result->safeContext)->toBe(['scenario' => 'fixture']);
})->with([
    'timeout' => ['The provider timed out.', null],
    'unauthorized' => ['The provider rejected the request.', 401],
    'forbidden' => ['The provider rejected the request.', 403],
    'rate limited' => ['The provider rate limit was reached.', 429],
    'server error' => ['The provider failed.', 500],
]);

it('supports current, date, and date-range requests', function (): void {
    $requests = [
        drawRequest(),
        drawRequest(date: new DateTimeImmutable('2026-08-31')),
        drawRequest(rangeStart: new DateTimeImmutable('2026-08-30'), rangeEnd: new DateTimeImmutable('2026-08-31')),
    ];
    $provider = new FakeLotteryDrawProvider(responder: static function (DrawFetchRequest $request): DrawFetchResult {
        return DrawFetchResult::available([['request_provider' => $request->provider]]);
    });

    expect($provider->capabilities()->current)->toBeTrue()
        ->and($provider->capabilities()->date)->toBeTrue()
        ->and($provider->capabilities()->range)->toBeTrue();

    foreach ($requests as $request) {
        expect($provider->fetch($request)->status)->toBe(DrawFetchResult::AVAILABLE);
    }
});

it('is registered as the default fake provider', function (): void {
    $resolver = app(LotteryDrawProviderResolver::class);

    expect($resolver->resolve(force: true))->toBeInstanceOf(FakeLotteryDrawProvider::class);
});

it('returns a default payload accepted by the real normalizer', function (): void {
    $payload = (new FakeLotteryDrawProvider)->fetch(drawRequest())->payloads[0];
    $normalizer = new ProviderPayloadNormalizer(static fn (int $id): bool => $id === 4);
    $normalized = $normalizer->normalize($payload, 'fake', 4, new DateTimeImmutable);

    expect($normalized)->toBeInstanceOf(NormalizedDrawData::class)
        ->and($normalized->p1->value())->toBe('04')
        ->and($normalized->p2->value())->toBe('00')
        ->and($normalized->p3->value())->toBe('97');
});

/** @return array<string, mixed> */
function drawFixture(string $name): array
{
    $contents = file_get_contents(base_path("tests/Fixtures/Draws/elboletoganador/{$name}.json"));

    if ($contents === false) {
        throw new RuntimeException("Unable to read draw fixture [{$name}].");
    }

    $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($payload)) {
        throw new RuntimeException("The draw fixture [{$name}] must decode to an object.");
    }

    return $payload;
}

function drawRequest(?DateTimeImmutable $date = null, ?DateTimeImmutable $rangeStart = null, ?DateTimeImmutable $rangeEnd = null): DrawFetchRequest
{
    return new DrawFetchRequest('fake', 4, SyncTrigger::Manual, $date, $rangeStart, $rangeEnd);
}
