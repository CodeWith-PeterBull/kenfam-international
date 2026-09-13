<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that a web order is ready for its selected fulfillment method. */
final readonly class OrderReady implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public string $orderUlid) {}
}
