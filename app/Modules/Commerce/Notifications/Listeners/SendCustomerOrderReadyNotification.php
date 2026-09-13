<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications\Listeners;

use App\Modules\Commerce\Notifications\CustomerOrderReadyNotification;
use App\Modules\Commerce\Notifications\OperationalNotificationDispatcher;
use App\Modules\Commerce\Orders\Events\OrderReady;

/** Routes a committed ready-state update to its order snapshot email. */
final readonly class SendCustomerOrderReadyNotification
{
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    public function handle(OrderReady $event): void
    {
        $this->notifications->toOrderCustomer(
            eventKey: 'order_ready',
            orderUlid: $event->orderUlid,
            notification: new CustomerOrderReadyNotification($event->orderUlid),
        );
    }
}
