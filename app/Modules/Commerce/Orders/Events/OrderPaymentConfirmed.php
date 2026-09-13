<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that a completed web-order payment has committed. */
final readonly class OrderPaymentConfirmed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public string $orderUlid,
        public string $paymentUlid,
    ) {}
}
