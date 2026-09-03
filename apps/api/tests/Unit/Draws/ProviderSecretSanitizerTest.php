<?php

use App\Infrastructure\Draws\Security\ProviderSecretSanitizer;

it('redacts the configured key from provider URLs and exception messages', function (): void {
    $key = 'provider-key-for-tests';
    $sanitizer = new ProviderSecretSanitizer($key);

    $url = 'https://provider.example.test/api/sorteos/'.$key.'/4';

    expect($sanitizer->sanitize($url))->toBe('https://provider.example.test/api/sorteos/[REDACTED]/4')
        ->and($sanitizer->sanitizeException(new RuntimeException('Request failed for '.$url)))->not->toContain($key)
        ->and($sanitizer->sanitizeException(new RuntimeException('Request failed for '.$url)))->toContain('[REDACTED]');
});

it('redacts nested JSON values, headers and cookies without retaining their original values', function (): void {
    $key = 'provider-key-for-tests';
    $sanitizer = new ProviderSecretSanitizer($key);

    $context = [
        'payload' => [
            'request_url' => 'https://provider.example.test/api/sorteos/'.$key.'/4',
            'credentials' => ['api_key' => $key],
        ],
        'headers' => [
            'Authorization' => 'Bearer '.$key,
            'X-Request-Id' => 'request-123',
        ],
        'cookies' => ['session' => 'cookie-value'],
    ];

    $sanitized = $sanitizer->sanitize($context);

    expect($sanitized)->toBe([
        'payload' => [
            'request_url' => 'https://provider.example.test/api/sorteos/[REDACTED]/4',
            'credentials' => ['api_key' => '[REDACTED]'],
        ],
        'headers' => [
            'Authorization' => '[REDACTED]',
            'X-Request-Id' => 'request-123',
        ],
        'cookies' => '[REDACTED]',
    ])
        ->and(json_encode($sanitized, JSON_THROW_ON_ERROR))->not->toContain($key)
        ->and(json_encode($sanitized, JSON_THROW_ON_ERROR))->not->toContain('cookie-value');
});
