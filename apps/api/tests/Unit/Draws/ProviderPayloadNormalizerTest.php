<?php

use App\Application\Draws\Data\NormalizedDrawData;
use App\Application\Draws\Normalization\NormalizedPayloadFailure;
use App\Application\Draws\Normalization\ProviderPayloadNormalizer;
use App\Infrastructure\Draws\Security\ProviderSecretSanitizer;

beforeEach(function (): void {
    $this->normalizer = new ProviderPayloadNormalizer(static fn (int $externalId): bool => $externalId === 4);
    $this->receivedAt = new DateTimeImmutable('2026-09-02T12:00:00Z');
});

it('normalizes a direct provider object preserving zeroes and converting the local draw time to UTC', function (): void {
    $result = $this->normalizer->normalize(providerPayload(['premios' => '04-00-97']), 'elboletoganador', 4, $this->receivedAt);

    expect($result)->toBeInstanceOf(NormalizedDrawData::class)
        ->and($result->externalDrawId)->toBe('227821')
        ->and($result->lotteryExternalId)->toBe(4)
        ->and($result->p1->value())->toBe('04')
        ->and($result->p2->value())->toBe('00')
        ->and($result->p3->value())->toBe('97')
        ->and($result->drawDateLocal->format('Y-m-d'))->toBe('2026-08-31')
        ->and($result->drawnAtUtc?->format('Y-m-d H:i:sP'))->toBe('2026-09-01 01:01:31+00:00')
        ->and($result->scheduledAtUtc?->format(DATE_ATOM))->toBe($result->drawnAtUtc?->format(DATE_ATOM));
});

it('keeps the material hash stable when only updated_at changes', function (): void {
    $first = $this->normalizer->normalize(providerPayload(['updated_at' => '2026-09-01T01:02:06Z']), 'elboletoganador', 4, $this->receivedAt);
    $second = $this->normalizer->normalize(providerPayload(['updated_at' => '2026-09-02T01:02:06Z']), 'elboletoganador', 4, $this->receivedAt);

    expect($first)->toBeInstanceOf(NormalizedDrawData::class)
        ->and($second)->toBeInstanceOf(NormalizedDrawData::class)
        ->and($first->sourceHash)->toBe($second->sourceHash);
});

it('returns a typed failure for non-contractual payloads and invalid fields', function (mixed $payload, string $code): void {
    $result = $this->normalizer->normalize($payload, 'elboletoganador', 4, $this->receivedAt);

    expect($result)->toBeInstanceOf(NormalizedPayloadFailure::class)
        ->and($result->code)->toBe($code);
})->with([
    'wrapper list' => [[providerPayload()], 'invalid_shape'],
    'incompatible lottery ID' => [providerPayload(['loteria_id' => 5]), 'incompatible_lottery_id'],
    'invalid date' => [providerPayload(['fecha_sorteo' => '2026-02-30']), 'invalid_draw_date'],
    'two prizes' => [providerPayload(['premios' => '04-00']), 'invalid_prizes'],
    'four prizes' => [providerPayload(['premios' => '04-00-97-12']), 'invalid_prizes'],
    'too many digits' => [providerPayload(['premios' => '04-00-105']), 'invalid_prizes'],
    'letters' => [providerPayload(['premios' => '04-AA-97']), 'invalid_prizes'],
    'null prizes' => [providerPayload(['premios' => null]), 'invalid_prizes'],
]);

it('rejects an unknown lottery without attempting to create it', function (): void {
    $result = $this->normalizer->normalize(providerPayload(['loteria_id' => 999]), 'elboletoganador', 999, $this->receivedAt);

    expect($result)->toBeInstanceOf(NormalizedPayloadFailure::class)
        ->and($result->code)->toBe('unknown_lottery')
        ->and($result->lotteryExternalId)->toBe(999);
});

it('sanitizes nested secret-bearing values, reflected URLs, and non-JSON values in failures', function (): void {
    $providerKey = 'never-persist-me';
    $payload = providerPayload(['premios' => '04-00-105', 'token' => $providerKey, 'detalle' => 'https://api.elboletoganador.com/api/sorteos/'.$providerKey.'/4', 'nested' => ['api_key' => 'also-redacted'], 'invalid' => new stdClass]);
    $result = $this->normalizer->normalize($payload, 'elboletoganador', 4, $this->receivedAt);

    expect($result)->toBeInstanceOf(NormalizedPayloadFailure::class)
        ->and($result->rawPayload['token'])->toBe('[REDACTED]')
        ->and($result->rawPayload['detalle'])->toBe('https://api.elboletoganador.com/api/sorteos/[REDACTED]/4')
        ->and(json_encode($result->rawPayload, JSON_THROW_ON_ERROR))->not->toContain($providerKey)
        ->and($result->rawPayload['nested']['api_key'])->toBe('[REDACTED]')
        ->and($result->rawPayload['invalid'])->toBe(['type' => 'object', 'class' => stdClass::class]);
});

it('uses the configured provider sanitizer for secret values reflected in arbitrary strings', function (): void {
    $providerKey = 'provider-key-for-tests';
    $normalizer = new ProviderPayloadNormalizer(static fn (int $externalId): bool => $externalId === 4, new ProviderSecretSanitizer($providerKey));
    $result = $normalizer->normalize(providerPayload(['premios' => '04-00-105', 'debug' => $providerKey]), 'elboletoganador', 4, $this->receivedAt);

    expect($result)->toBeInstanceOf(NormalizedPayloadFailure::class)
        ->and($result->rawPayload['debug'])->toBe('[REDACTED]')
        ->and(json_encode($result->rawPayload, JSON_THROW_ON_ERROR))->not->toContain($providerKey);
});

/** @return array<string, mixed> */
function providerPayload(array $overrides = []): array
{
    return array_replace([
        'id' => 227821,
        'loteria_id' => 4,
        'fecha_sorteo' => '2026-08-31',
        'premios' => '50-32-77',
        'hora' => '21:01:31',
    ], $overrides);
}
