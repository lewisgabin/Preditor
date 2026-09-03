<?php

use App\Application\Draws\Contracts\LotteryDrawProvider;
use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Data\DrawFetchResult;
use App\Application\Draws\Data\DrawProviderCapabilities;
use App\Infrastructure\Draws\Providers\LotteryDrawProviderResolver;

it('uses fake as the testing provider and keeps provider configuration outside API resources', function (): void {
    expect(config('lottery-api.provider'))->toBe('fake')
        ->and(config('lottery-api.enabled'))->toBeFalse()
        ->and(config('lottery-api.key'))->toBe('')
        ->and(config('lottery-api.base_url'))->toBe('')
        ->and(config('lottery-api'))->not->toHaveKey('resource');
});

it('resolves only registered providers and requires explicit selection for the real provider', function (): void {
    $fake = providerStub();
    $real = providerStub();

    config()->set('lottery-api.enabled', true);
    config()->set('lottery-api.provider', 'fake');

    $resolver = new LotteryDrawProviderResolver([
        'fake' => $fake,
        'elboletoganador' => $real,
    ]);

    expect($resolver->resolve())->toBe($fake)
        ->and($resolver->resolve('elboletoganador'))->toBe($real);
});

it('does not resolve an enabled provider while integration is disabled unless a future manual force requests it', function (): void {
    config()->set('lottery-api.enabled', false);
    config()->set('lottery-api.provider', 'fake');

    $resolver = new LotteryDrawProviderResolver(['fake' => providerStub()]);

    expect(fn (): LotteryDrawProvider => $resolver->resolve())->toThrow(LogicException::class)
        ->and($resolver->resolve(force: true))->toBeInstanceOf(LotteryDrawProvider::class);
});

function providerStub(): LotteryDrawProvider
{
    return new class implements LotteryDrawProvider
    {
        public function capabilities(): DrawProviderCapabilities
        {
            return new DrawProviderCapabilities(current: true, date: true, range: true);
        }

        public function fetch(DrawFetchRequest $request): DrawFetchResult
        {
            return DrawFetchResult::notAvailable();
        }
    };
}
