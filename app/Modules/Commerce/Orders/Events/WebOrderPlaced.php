<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that a storefront order has committed successfully. */
final readonly class WebOrderPlaced implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public string $orderUlid) {}
}
