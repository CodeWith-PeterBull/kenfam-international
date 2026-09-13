<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Support;

/**
 * Formats configured currency values directly from integer minor units.
 */
final class MoneyFormatter
{
    /**
     * Return a localized-looking display value without floating-point conversion.
     */
    public static function format(int $minor, bool $useSymbol = true): string
    {
        $scale = max(0, min(6, (int) config('commerce.currency.decimal_places', 2)));
        $decimal = ScaledDecimal::formatUnsigned($minor, $scale);
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $amount = number_format((int) $whole);

        if ($scale > 0) {
            $amount .= '.'.$fraction;
        }

        $currency = $useSymbol
            ? trim((string) config('commerce.currency.symbol', 'KSh'))
            : strtoupper(trim((string) config('commerce.currency.code', 'KES')));

        return trim($currency.' '.$amount);
    }

    private function __construct() {}
}
