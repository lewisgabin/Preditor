<?php

namespace App\Infrastructure\Draws\Providers;

use App\Application\Draws\Contracts\LotteryDrawProvider;
use App\Application\Draws\Data\DrawFetchRequest;
use App\Application\Draws\Data\DrawFetchResult;
use App\Application\Draws\Data\DrawProviderCapabilities;
use App\Domain\Draws\Enums\SyncTrigger;
use App\Infrastructure\Draws\Exceptions\SafeProviderException;
use App\Infrastructure\Draws\Security\ProviderSecretSanitizer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final readonly class HttpLotteryDrawProvider implements LotteryDrawProvider
{
    private const string TIMEZONE = 'America/Santo_Domingo';

    private ProviderSecretSanitizer $sanitizer;

    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private int $timeoutSeconds = 10,
        private int $connectTimeoutSeconds = 5,
        ?ProviderSecretSanitizer $sanitizer = null,
    ) {
        $this->sanitizer = $sanitizer ?? new ProviderSecretSanitizer($this->apiKey);
    }

    public function capabilities(): DrawProviderCapabilities
    {
        return new DrawProviderCapabilities(current: true, date: false, range: false);
    }

    public function fetch(DrawFetchRequest $request): DrawFetchResult
    {
        if (($unsupportedRequest = $this->unsupportedRequest($request)) !== null) {
            return $this->failure($unsupportedRequest);
        }

        if (trim($this->baseUrl) === '' || trim($this->apiKey) === '') {
            return $this->failure(new SafeProviderException('The lottery draw provider is not configured.', safeContext: ['category' => 'configuration']));
        }

        try {
            $response = Http::acceptJson()
                ->timeout(max(1, $this->timeoutSeconds))
                ->connectTimeout(max(1, $this->connectTimeoutSeconds))
                ->get($this->endpointFor($request));

            if (! $response->successful()) {
                return $this->failure($this->httpFailure($response));
            }

            $payload = $response->json();

            if ($payload === null || $payload === []) {
                return DrawFetchResult::notAvailable();
            }

            if (! is_array($payload)) {
                return $this->failure(new SafeProviderException('The lottery draw provider returned an invalid payload.', $response->status(), ['category' => 'invalid_payload']));
            }

            if (! array_is_list($payload) && $this->isOlderThanRequestedDate($payload, $request)) {
                return DrawFetchResult::notAvailable();
            }

            return DrawFetchResult::available([$payload]);
        } catch (Throwable $exception) {
            return $this->failure($this->exceptionFailure($exception));
        }
    }

    private function unsupportedRequest(DrawFetchRequest $request): ?SafeProviderException
    {
        if ($request->trigger === SyncTrigger::Reconciliation) {
            return new SafeProviderException('The lottery draw provider does not support reconciliation requests.', safeContext: ['category' => 'unsupported_request']);
        }

        if ($request->trigger === SyncTrigger::Historical || $request->date !== null || $request->rangeStart !== null || $request->rangeEnd !== null) {
            return new SafeProviderException('The lottery draw provider only supports current draw requests.', safeContext: ['category' => 'unsupported_request']);
        }

        return null;
    }

    private function endpointFor(DrawFetchRequest $request): string
    {
        return rtrim($this->baseUrl, '/').'/api/sorteos/'.rawurlencode($this->apiKey).'/'.$request->lotteryExternalId;
    }

    /** @param array<mixed> $payload */
    private function isOlderThanRequestedDate(array $payload, DrawFetchRequest $request): bool
    {
        $drawDate = $payload['fecha_sorteo'] ?? null;

        if (! is_string($drawDate)) {
            return false;
        }

        try {
            $receivedDate = new DateTimeImmutable($drawDate, new DateTimeZone(self::TIMEZONE));
            $requestedDate = $request->date ?? new DateTimeImmutable('now', new DateTimeZone(self::TIMEZONE));

            return $receivedDate->setTimezone(new DateTimeZone(self::TIMEZONE))->format('Y-m-d') < $requestedDate->setTimezone(new DateTimeZone(self::TIMEZONE))->format('Y-m-d');
        } catch (Throwable) {
            return false;
        }
    }

    private function httpFailure(Response $response): SafeProviderException
    {
        $status = $response->status();
        $context = ['category' => match (true) {
            $status === 429 => 'rate_limited',
            $status === 401 || $status === 403 => 'authentication',
            $status === 408 || $status >= 500 => 'temporary',
            default => 'permanent',
        }];
        $retryAfter = trim((string) $response->header('Retry-After'));

        if ($retryAfter !== '') {
            $context['retry_after'] = $retryAfter;
        }

        $message = match (true) {
            $status === 429 => 'The lottery draw provider rate limit was reached.',
            $status === 401 || $status === 403 => 'The lottery draw provider rejected the request.',
            $status === 408 => 'The lottery draw provider request timed out.',
            $status >= 500 => 'The lottery draw provider is temporarily unavailable.',
            default => 'The lottery draw provider rejected the request.',
        };

        return new SafeProviderException($message, $status, $context);
    }

    private function exceptionFailure(Throwable $exception): SafeProviderException
    {
        $message = strtolower($this->sanitizer->sanitizeException($exception));
        $timedOut = $exception instanceof ConnectionException && str_contains($message, 'timed out');

        return new SafeProviderException(
            $timedOut ? 'The lottery draw provider request timed out.' : 'The lottery draw provider network request failed.',
            safeContext: ['category' => $timedOut ? 'timeout' : 'network'],
        );
    }

    private function failure(SafeProviderException $exception): DrawFetchResult
    {
        return DrawFetchResult::failure($exception->getMessage(), $exception->httpStatus, $this->sanitizer->sanitize($exception->safeContext));
    }
}
