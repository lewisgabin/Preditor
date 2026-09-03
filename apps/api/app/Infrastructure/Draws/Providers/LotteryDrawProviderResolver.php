<?php

namespace App\Infrastructure\Draws\Providers;

use App\Application\Draws\Contracts\LotteryDrawProvider;
use InvalidArgumentException;
use LogicException;

final readonly class LotteryDrawProviderResolver
{
    /** @var array<string, LotteryDrawProvider> */
    private array $providers;

    /** @param array<array-key, mixed> $providers */
    public function __construct(array $providers, private ?string $configuredProvider = null, private ?bool $enabled = null)
    {
        $this->providers = $this->validateProviders($providers);
    }

    /**
     * @param  array<array-key, mixed>  $providers
     * @return array<string, LotteryDrawProvider>
     */
    private function validateProviders(array $providers): array
    {
        $validatedProviders = [];

        foreach ($providers as $name => $provider) {
            if (! is_string($name) || ! $provider instanceof LotteryDrawProvider) {
                throw new InvalidArgumentException('The provider registry must contain named LotteryDrawProvider instances.');
            }

            $validatedProviders[$name] = $provider;
        }

        return $validatedProviders;
    }

    public function resolve(?string $provider = null, bool $force = false): LotteryDrawProvider
    {
        if (! $force && ! ($this->enabled ?? (bool) config('lottery-api.enabled'))) {
            throw new LogicException('Lottery draw provider integration is disabled.');
        }

        $name = $provider ?? $this->configuredProvider ?? (string) config('lottery-api.provider');

        if (! in_array($name, ['fake', 'elboletoganador'], true)) {
            throw new InvalidArgumentException('The configured lottery draw provider is not supported.');
        }

        if (! array_key_exists($name, $this->providers)) {
            throw new InvalidArgumentException('The configured lottery draw provider is not registered.');
        }

        return $this->providers[$name];
    }
}
