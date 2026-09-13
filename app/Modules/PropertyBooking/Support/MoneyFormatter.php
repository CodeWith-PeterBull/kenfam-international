<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Support;

/** Formats integer booking money without introducing floating-point arithmetic. */
final class MoneyFormatter
{
    /** Format a minor-unit amount with the configured or supplied currency. */
    public static function format(int $minor, ?string $currency = null): string
    {
        $decimals = max(0, min(4, (int) config('property-booking.defaults.currency_decimals', 2)));
        $symbol = (string) config('property-booking.defaults.currency_symbol', 'KSh');
        $configuredCurrency = strtoupper((string) config('property-booking.defaults.currency', 'KES'));
        $currency = strtoupper(trim((string) ($currency ?: $configuredCurrency)));
        $prefix = $currency === $configuredCurrency && $symbol !== '' ? $symbol : $currency;

        return $prefix.' '.ScaledDecimal::formatUnsigned(max(0, $minor), $decimals);
    }
}
