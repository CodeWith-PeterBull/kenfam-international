<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Support;

use App\Modules\PropertyBooking\Exceptions\PropertyBookingException;

/** Overflow-checked integer arithmetic for prices, tax, and deposits. */
final class MoneyMath
{
    /** Add integer monetary amounts without floating-point arithmetic. */
    public static function add(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || $left > PHP_INT_MAX - $right) {
            throw new PropertyBookingException('The requested amount exceeds supported monetary limits.');
        }

        return $left + $right;
    }

    /** Multiply an integer monetary amount safely. */
    public static function multiply(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || ($right !== 0 && $left > intdiv(PHP_INT_MAX, $right))) {
            throw new PropertyBookingException('The requested amount exceeds supported monetary limits.');
        }

        return $left * $right;
    }

    /** Calculate a basis-point percentage using deterministic rounding. */
    public static function percentage(int $amount, int $basisPoints, bool $inclusive = false): int
    {
        if ($basisPoints < 0 || $basisPoints > 10_000) {
            throw new PropertyBookingException('Basis points must be between zero and 10,000.');
        }

        if ($amount === 0 || $basisPoints === 0) {
            return 0;
        }

        $denominator = $inclusive ? 10_000 + $basisPoints : 10_000;
        $numerator = self::multiply($amount, $basisPoints);

        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    /** Prevent direct utility instantiation. */
    private function __construct() {}
}
