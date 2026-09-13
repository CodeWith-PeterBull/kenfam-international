<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications\Listeners;

use App\Modules\Commerce\Notifications\CustomerOrderCancelledNotification;
use App\Modules\Commerce\Notifications\OperationalNotificationDispatcher;
use App\Modules\Commerce\Orders\Events\OrderCancelled;

/** Routes a committed cancellation update to its order snapshot email. */
final readonly class SendCustomerOrderCancelledNotification
{
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    public function handle(OrderCancelled $event): void
    {
        $this->notifications->toOrderCustomer(
            eventKey: 'order_cancelled',
            orderUlid: $event->orderUlid,
            notification: new CustomerOrderCancelledNotification($event->orderUlid),
        );
    }
}
