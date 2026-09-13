<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications\Listeners;

use App\Modules\Commerce\Notifications\CustomerPaymentConfirmedNotification;
use App\Modules\Commerce\Notifications\OperationalNotificationDispatcher;
use App\Modules\Commerce\Orders\Events\OrderPaymentConfirmed;

/** Routes a committed web payment confirmation to its order snapshot email. */
final readonly class SendCustomerPaymentConfirmedNotification
{
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    public function handle(OrderPaymentConfirmed $event): void
    {
        $this->notifications->toOrderCustomer(
            eventKey: 'payment_confirmed',
            orderUlid: $event->orderUlid,
            notification: new CustomerPaymentConfirmedNotification($event->orderUlid, $event->paymentUlid),
        );
    }
}
