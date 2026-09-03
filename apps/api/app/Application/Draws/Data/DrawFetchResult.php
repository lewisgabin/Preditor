<?php

namespace App\Application\Draws\Data;

use InvalidArgumentException;

final readonly class DrawFetchResult
{
    public const string AVAILABLE = 'available';

    public const string NOT_AVAILABLE = 'not_available';

    public const string FAILURE = 'failure';

    /** @var array<mixed> */
    public array $safeContext;

    /**
     * @param  array<mixed>  $payloads
     * @param  array<mixed>  $safeContext
     */
    public function __construct(
        public string $status,
        public array $payloads = [],
        public ?string $failureReason = null,
        public ?int $httpStatus = null,
        array $safeContext = [],
    ) {
        if (! in_array($this->status, [self::AVAILABLE, self::NOT_AVAILABLE, self::FAILURE], true)) {
            throw new InvalidArgumentException('The draw fetch result status is invalid.');
        }

        if ($this->httpStatus !== null && ($this->httpStatus < 100 || $this->httpStatus > 599)) {
            throw new InvalidArgumentException('The HTTP status must be between 100 and 599.');
        }

        $this->assertPayloadsAreArrays();
        $this->safeContext = self::sanitizeContext($safeContext);

        match ($this->status) {
            self::AVAILABLE => $this->assertAvailable(),
            self::NOT_AVAILABLE => $this->assertNotAvailable(),
            self::FAILURE => $this->assertFailure(),
        };
    }

    /** @param non-empty-list<array<mixed>> $payloads */
    public static function available(array $payloads): self
    {
        return new self(self::AVAILABLE, $payloads);
    }

    public static function notAvailable(): self
    {
        return new self(self::NOT_AVAILABLE);
    }

    /** @param array<mixed> $safeContext */
    public static function failure(string $reason, ?int $httpStatus = null, array $safeContext = []): self
    {
        return new self(self::FAILURE, failureReason: $reason, httpStatus: $httpStatus, safeContext: $safeContext);
    }

    private function assertPayloadsAreArrays(): void
    {
        if (! array_is_list($this->payloads)) {
            throw new InvalidArgumentException('The draw payloads must be a list.');
        }

        foreach ($this->payloads as $payload) {
            if (! is_array($payload)) {
                throw new InvalidArgumentException('Each draw payload must be an array.');
            }
        }
    }

    private function assertAvailable(): void
    {
        if ($this->payloads === []) {
            throw new InvalidArgumentException('An available result requires at least one draw payload.');
        }

        if ($this->failureReason !== null || $this->httpStatus !== null || $this->safeContext !== []) {
            throw new InvalidArgumentException('An available result cannot include failure details.');
        }
    }

    private function assertNotAvailable(): void
    {
        if ($this->payloads !== [] || $this->failureReason !== null || $this->httpStatus !== null || $this->safeContext !== []) {
            throw new InvalidArgumentException('A not available result cannot include payloads or failure details.');
        }
    }

    private function assertFailure(): void
    {
        if ($this->payloads !== [] || $this->failureReason === null || trim($this->failureReason) === '') {
            throw new InvalidArgumentException('A failure result requires a safe reason and cannot include payloads.');
        }
    }

    /** @param array<mixed> $context
     * @return array<mixed>
     */
    private static function sanitizeContext(array $context): array
    {
        $safeContext = [];

        foreach ($context as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('The safe context keys must be strings.');
            }

            if (preg_match('/authorization|cookie|password|secret|token|api[-_]?key/i', $key) === 1) {
                $safeContext[$key] = '[redacted]';

                continue;
            }

            if (is_array($value)) {
                $safeContext[$key] = self::sanitizeContext($value);

                continue;
            }

            if (! is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('The safe context values must be scalars, arrays, or null.');
            }

            $safeContext[$key] = $value;
        }

        return $safeContext;
    }
}
