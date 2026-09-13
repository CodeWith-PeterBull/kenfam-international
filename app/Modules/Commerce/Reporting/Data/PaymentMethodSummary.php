<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Reporting\Data;

use App\Modules\Commerce\Orders\Enums\PaymentMethod;

/**
 * Selected-period completed-payment total for one tender method.
 */
final readonly class PaymentMethodSummary
{
    public function __construct(
        public PaymentMethod $method,
        public int $paymentCount,
        public int $totalMinor,
    ) {}
}
