<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Support;

use InvalidArgumentException;

/**
 * Converts unsigned decimal input to exact scaled integers without floats.
 */
final class ScaledDecimal
{
    /**
     * Parse a decimal with the requested scale.
     */
    public static function parseUnsigned(string $value, int $scale): int
    {
        $value = trim($value);
        if ($scale < 0 || $scale > 6) {
            throw new InvalidArgumentException('Decimal scale must be between zero and six.');
        }

        $pattern = $scale === 0 ? '/^\d+$/' : '/^\d+(?:\.\d{1,'.$scale.'})?$/';
        if (preg_match($pattern, $value) !== 1) {
            throw new InvalidArgumentException("The value must have at most {$scale} decimal places.");
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $factor = 10 ** $scale;
        $normalizedWhole = ltrim($whole, '0') ?: '0';
        $maximumWhole = (string) intdiv(PHP_INT_MAX, $factor);

        if (strlen($normalizedWhole) > strlen($maximumWhole)
            || (strlen($normalizedWhole) === strlen($maximumWhole) && strcmp($normalizedWhole, $maximumWhole) > 0)) {
            throw new InvalidArgumentException('The decimal value is too large.');
        }

        $scaledWhole = (int) $normalizedWhole * $factor;
        $scaledFraction = $scale === 0 ? 0 : (int) str_pad($fraction, $scale, '0');
        if ($scaledWhole > PHP_INT_MAX - $scaledFraction) {
            throw new InvalidArgumentException('The decimal value is too large.');
        }

        return $scaledWhole + $scaledFraction;
    }

    /**
     * Format a scaled integer for an editable decimal input.
     */
    public static function formatUnsigned(?int $value, int $scale): string
    {
        if ($value === null) {
            return '';
        }

        if ($value < 0 || $scale < 0 || $scale > 6) {
            throw new InvalidArgumentException('Only non-negative scaled values are supported.');
        }

        if ($scale === 0) {
            return (string) $value;
        }

        $factor = 10 ** $scale;

        return intdiv($value, $factor).'.'.str_pad((string) ($value % $factor), $scale, '0', STR_PAD_LEFT);
    }

    /** Prevent direct utility instantiation. */
    private function __construct() {}
}
