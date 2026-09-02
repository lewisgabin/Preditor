<?php

namespace App\Application\Draws\Normalization;

use App\Application\Draws\Data\NormalizedDrawData;
use App\Domain\Draws\Enums\DrawStatus;
use App\Domain\Draws\ValueObjects\LotteryNumber;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final readonly class ProviderPayloadNormalizer
{
    private const string TIMEZONE = 'America/Santo_Domingo';

    /** @var \Closure(int): bool */
    private \Closure $lotteryExists;

    /**
     * The lookup is deliberately supplied by the application boundary. This keeps
     * Eloquent models out of normalized DTOs and prevents a provider payload from
     * creating a lottery implicitly.
     *
     * @param  \Closure(int): bool  $lotteryExists
     */
    public function __construct(\Closure $lotteryExists)
    {
        $this->lotteryExists = $lotteryExists;
    }

    public function normalize(
        mixed $payload,
        string $provider,
        int $requestedLotteryExternalId,
        DateTimeImmutable $receivedAt,
    ): NormalizedDrawData|NormalizedPayloadFailure {
        $rawPayload = $this->sanitizePayload($payload);

        if (! ($this->lotteryExists)($requestedLotteryExternalId)) {
            return $this->failure('unknown_lottery', 'The requested lottery does not exist.', $rawPayload, $requestedLotteryExternalId);
        }

        if (! is_array($payload) || array_is_list($payload)) {
            return $this->failure('invalid_shape', 'The provider payload must be a direct object.', $rawPayload);
        }

        $lotteryExternalId = $this->positiveInteger($payload['loteria_id'] ?? null);

        if ($lotteryExternalId === null) {
            return $this->failure('invalid_lottery_id', 'The provider lottery ID is invalid.', $rawPayload);
        }

        if ($lotteryExternalId !== $requestedLotteryExternalId) {
            return $this->failure('incompatible_lottery_id', 'The provider lottery ID does not match the requested lottery.', $rawPayload, $lotteryExternalId);
        }

        if (! ($this->lotteryExists)($lotteryExternalId)) {
            return $this->failure('unknown_lottery', 'The provider lottery does not exist.', $rawPayload, $lotteryExternalId);
        }

        $externalDrawId = $this->externalDrawId($payload['id'] ?? null);

        if ($externalDrawId === null) {
            return $this->failure('invalid_external_draw_id', 'The provider draw ID is invalid.', $rawPayload, $lotteryExternalId);
        }

        $drawDateLocal = $this->localDate($payload['fecha_sorteo'] ?? null);

        if ($drawDateLocal === null) {
            return $this->failure('invalid_draw_date', 'The provider draw date is invalid.', $rawPayload, $lotteryExternalId);
        }

        $drawnAtUtc = $this->drawnAtUtc($drawDateLocal, $payload['hora'] ?? null);

        $numbers = $this->numbers($payload['premios'] ?? null);

        if ($numbers === null) {
            return $this->failure('invalid_prizes', 'The provider prizes must contain exactly three lottery numbers.', $rawPayload, $lotteryExternalId);
        }

        try {
            [$p1, $p2, $p3] = [new LotteryNumber($numbers[0]), new LotteryNumber($numbers[1]), new LotteryNumber($numbers[2])];
        } catch (InvalidArgumentException) {
            return $this->failure('invalid_prizes', 'The provider prizes contain an invalid lottery number.', $rawPayload, $lotteryExternalId);
        }

        $sourceHash = hash('sha256', json_encode([
            'provider' => $provider,
            'external_draw_id' => $externalDrawId,
            'lottery_external_id' => $lotteryExternalId,
            'draw_date_local' => $drawDateLocal->format('Y-m-d'),
            'drawn_at_utc' => $drawnAtUtc?->format(DATE_ATOM),
            'p1' => $p1->value(),
            'p2' => $p2->value(),
            'p3' => $p3->value(),
        ], JSON_THROW_ON_ERROR));

        return new NormalizedDrawData(
            $lotteryExternalId,
            $provider,
            $externalDrawId,
            $drawDateLocal,
            $drawnAtUtc,
            $drawnAtUtc,
            $p1,
            $p2,
            $p3,
            DrawStatus::Confirmed,
            $sourceHash,
            $rawPayload,
            $receivedAt,
        );
    }

    /** @return list<string>|null */
    private function numbers(mixed $prizes): ?array
    {
        if (! is_string($prizes)) {
            return null;
        }

        $numbers = explode('-', $prizes);

        return count($numbers) === 3 ? $numbers : null;
    }

    private function localDate(mixed $date): ?DateTimeImmutable
    {
        if (! is_string($date)) {
            return null;
        }

        return $this->dateFromFormat('!Y-m-d', $date);
    }

    private function drawnAtUtc(DateTimeImmutable $drawDateLocal, mixed $time): ?DateTimeImmutable
    {
        if ($time === null) {
            return null;
        }

        if (! is_string($time)) {
            return null;
        }

        $local = $this->dateFromFormat('!Y-m-d H:i:s', $drawDateLocal->format('Y-m-d').' '.$time);

        return $local?->setTimezone(new DateTimeZone('UTC'));
    }

    private function dateFromFormat(string $format, string $value): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone(self::TIMEZONE));
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date->format(str_replace(['!', '|'], '', $format)) === $value ? $date : null;
    }

    private function positiveInteger(mixed $value): ?int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1 && (int) $value > 0) {
            return (int) $value;
        }

        return null;
    }

    private function externalDrawId(mixed $value): ?string
    {
        if (is_int($value) && $value > 0) {
            return (string) $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
            return $value;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function sanitizePayload(mixed $payload): array
    {
        if (! is_array($payload) || array_is_list($payload)) {
            return ['representation' => $this->sanitizeValue($payload)];
        }

        /** @var array<string, mixed> $sanitized */
        $sanitized = $this->sanitizeValue($payload);

        return $sanitized;
    }

    private function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $item) {
                $normalizedKey = is_int($key) ? $key : (string) $key;
                $sanitized[$normalizedKey] = is_string($normalizedKey) && $this->sensitiveKey($normalizedKey)
                    ? '[REDACTED]'
                    : $this->sanitizeValue($item);
            }

            return $sanitized;
        }

        if (is_resource($value)) {
            return ['type' => 'resource'];
        }

        if (is_object($value)) {
            return ['type' => 'object', 'class' => $value::class];
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        if (is_float($value) && ! is_finite($value)) {
            return ['type' => 'non_finite_float'];
        }

        return $value;
    }

    private function sensitiveKey(string $key): bool
    {
        return preg_match('/authorization|cookie|password|secret|token|api[-_]?key/i', $key) === 1;
    }

    private function sanitizeString(string $value): string
    {
        $sanitized = preg_replace('~/api/sorteos/[^/?#]+/~', '/api/sorteos/[REDACTED]/', $value) ?? $value;

        return preg_replace('/([?&](?:api[_-]?key|token|secret|password)=)[^&#\s]*/i', '$1[REDACTED]', $sanitized) ?? $sanitized;
    }

    /** @param array<string, mixed> $rawPayload */
    private function failure(string $code, string $message, array $rawPayload, ?int $lotteryExternalId = null): NormalizedPayloadFailure
    {
        return new NormalizedPayloadFailure($code, $message, $rawPayload, $lotteryExternalId);
    }
}
