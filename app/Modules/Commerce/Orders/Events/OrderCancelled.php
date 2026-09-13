<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Orders\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Signals that a web order was cancelled and its committed stock released. */
final readonly class OrderCancelled implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public string $orderUlid) {}
}
