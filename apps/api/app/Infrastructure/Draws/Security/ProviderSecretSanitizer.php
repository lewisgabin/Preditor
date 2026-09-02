<?php

namespace App\Infrastructure\Draws\Security;

use Throwable;

final readonly class ProviderSecretSanitizer
{
    private const REDACTED = '[REDACTED]';

    public function __construct(private string $apiKey = '') {}

    public function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        if (is_string($value)) {
            return $this->sanitizeString($value);
        }

        return $value;
    }

    public function sanitizeException(Throwable $exception): string
    {
        return $this->sanitizeString($exception->getMessage());
    }

    /**
     * @param  array<array-key, mixed>  $value
     * @return array<array-key, mixed>
     */
    private function sanitizeArray(array $value): array
    {
        $sanitized = [];

        foreach ($value as $key => $item) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $sanitized[$key] = self::REDACTED;

                continue;
            }

            $sanitized[$key] = $this->sanitize($item);
        }

        return $sanitized;
    }

    private function sanitizeString(string $value): string
    {
        $sanitized = preg_replace('~/api/sorteos/[^/?#]+/~', '/api/sorteos/'.self::REDACTED.'/', $value) ?? $value;
        $sanitized = preg_replace('/([?&](?:api[_-]?key|token|secret|password)=)[^&#\s]*/i', '$1'.self::REDACTED, $sanitized) ?? $sanitized;

        return $this->apiKey === '' ? $sanitized : str_replace($this->apiKey, self::REDACTED, $sanitized);
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/(?:authorization|api[_-]?key|token|secret|password|cookie)/i', $key);
    }
}
