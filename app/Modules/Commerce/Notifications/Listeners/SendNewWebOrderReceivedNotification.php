<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications\Listeners;

use App\Modules\Commerce\Notifications\NewWebOrderReceivedNotification;
use App\Modules\Commerce\Notifications\OperationalNotificationDispatcher;
use App\Modules\Commerce\Orders\Events\WebOrderPlaced;
use App\Modules\Commerce\Support\CommercePermission;

/** Routes new web-order alerts to active order managers. */
final readonly class SendNewWebOrderReceivedNotification
{
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    public function handle(WebOrderPlaced $event): void
    {
        $this->notifications->toStaff(
            eventKey: 'web_order_placed',
            permission: CommercePermission::MANAGE_ORDERS,
            subjectUlid: $event->orderUlid,
            notification: new NewWebOrderReceivedNotification($event->orderUlid),
        );
    }
}
