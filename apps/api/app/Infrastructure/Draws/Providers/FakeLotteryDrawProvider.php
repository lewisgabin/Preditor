<?php

namespace App\Infrastructure\Draws\Providers;

use App\Application\Draws\Contracts\LotteryDrawProvider;
use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Data\DrawFetchResult;
use App\Application\Draws\Data\DrawProviderCapabilities;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;

final readonly class FakeLotteryDrawProvider implements LotteryDrawProvider
{
    /** @var (Closure(DrawFetchRequest): DrawFetchResult)|null */
    private ?Closure $responder;

    public function __construct(?Closure $responder = null, private ?DrawFetchResult $defaultResult = null)
    {
        $this->responder = $responder;
    }

    public function capabilities(): DrawProviderCapabilities
    {
        return new DrawProviderCapabilities(current: true, date: true, range: true);
    }

    public function fetch(DrawFetchRequest $request): DrawFetchResult
    {
        if ($this->responder === null) {
            return $this->defaultResult ?? DrawFetchResult::available([[
                'id' => sprintf('fake-%s-%d', (new DateTimeImmutable('now', new DateTimeZone('America/Santo_Domingo')))->format('Ymd'), $request->lotteryExternalId),
                'loteria_id' => $request->lotteryExternalId,
                'fecha_sorteo' => (new DateTimeImmutable('now', new DateTimeZone('America/Santo_Domingo')))->format('Y-m-d'),
                'premios' => '04-00-97',
                'hora' => '12:00:00',
            ]]);
        }

        $result = ($this->responder)($request);

        if (! $result instanceof DrawFetchResult) {
            throw new LogicException('The fake lottery draw provider responder must return a DrawFetchResult.');
        }

        return $result;
    }
}
