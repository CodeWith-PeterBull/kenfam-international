<?php

/**
 * Provides exact formatting for TravelTours integer minor-unit values.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Support;

use InvalidArgumentException;

/** Convert non-negative scaled integers without floating-point arithmetic. */
final class ScaledDecimal
{
    /** Format an integer using the supplied ISO currency exponent. */
    public static function formatUnsigned(int $value, int $exponent): string
    {
        if ($value < 0 || $exponent < 0 || $exponent > 6) {
            throw new InvalidArgumentException('Money values must be non-negative and use an exponent between zero and six.');
        }
        if ($exponent === 0) {
            return (string) $value;
        }

        $factor = 10 ** $exponent;
        $whole = intdiv($value, $factor);
        $fraction = str_pad((string) ($value % $factor), $exponent, '0', STR_PAD_LEFT);

        return $whole.'.'.$fraction;
    }

    /**
     * Parse a validated non-negative decimal string into minor units without floats.
     *
     * The string must already match a `\d+(\.\d{1,exponent})?` rule; more
     * fraction digits than the exponent allows are refused, never rounded.
     */
    public static function toMinor(string $amount, int $exponent): int
    {
        $amount = trim($amount);
        if ($exponent < 0 || $exponent > 6 || preg_match('/^\d{1,12}(\.\d{1,'.max($exponent, 1).'})?$/', $amount) !== 1) {
            throw new InvalidArgumentException('Money input must be a non-negative decimal within the currency exponent.');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        if ($exponent === 0 && $fraction !== '') {
            throw new InvalidArgumentException('This currency does not use fractional amounts.');
        }

        return ((int) $whole * (10 ** $exponent)) + (int) str_pad($fraction, $exponent, '0');
    }

    /** Prevent instantiation of this stateless decimal utility. */
    private function __construct() {}
}
