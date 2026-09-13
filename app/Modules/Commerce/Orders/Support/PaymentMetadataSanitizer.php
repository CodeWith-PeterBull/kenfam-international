<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Support;

use BackedEnum;
use Illuminate\Support\Str;

/**
 * Removes credential-like payment context before persistence or activity use.
 */
final class PaymentMetadataSanitizer
{
    private const MAX_DEPTH = 4;

    private const MAX_ITEMS = 30;

    private const SENSITIVE_FRAGMENTS = [
        'authorization',
        'card_number',
        'credential',
        'cvv',
        'password',
        'pin',
        'private_key',
        'secret',
        'token',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function sanitize(array $metadata): array
    {
        return $this->sanitizeArray($metadata, 0);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private function sanitizeArray(array $values, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['_truncated' => 'Maximum metadata depth reached.'];
        }

        $sanitized = [];
        foreach (array_slice($values, 0, self::MAX_ITEMS, true) as $key => $value) {
            $sanitized[$key] = $this->isSensitive((string) $key)
                ? '[REDACTED]'
                : $this->sanitizeValue($value, $depth + 1);
        }

        if (count($values) > self::MAX_ITEMS) {
            $sanitized['_truncated'] = count($values) - self::MAX_ITEMS;
        }

        return $sanitized;
    }

    private function sanitizeValue(mixed $value, int $depth): mixed
    {
        if (is_array($value)) {
            return $this->sanitizeArray($value, $depth);
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if (is_string($value)) {
            return Str::limit(trim($value), 500, '');
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return '[Unsupported metadata value]';
    }

    private function isSensitive(string $key): bool
    {
        $key = Str::of($key)->snake()->lower()->toString();

        foreach (self::SENSITIVE_FRAGMENTS as $fragment) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
