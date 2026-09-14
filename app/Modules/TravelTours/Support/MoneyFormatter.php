<?php

/**
 * Formats TravelTours amounts from persisted integer minor units.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Support;

/** Render currency amounts while honoring their snapshotted exponent. */
final class MoneyFormatter
{
    /** Return an ISO-prefixed display value without floating-point conversion. */
    public static function format(int $minor, string $currency, int $exponent = 2): string
    {
        $decimal = ScaledDecimal::formatUnsigned($minor, $exponent);
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $amount = number_format((int) $whole);
        if ($exponent > 0) {
            $amount .= '.'.$fraction;
        }

        return strtoupper(trim($currency)).' '.$amount;
    }

    /** Prevent instantiation of this stateless formatting utility. */
    private function __construct() {}
}
