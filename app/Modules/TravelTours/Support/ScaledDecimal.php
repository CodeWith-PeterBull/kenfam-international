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

    /** Prevent instantiation of this stateless decimal utility. */
    private function __construct() {}
}
