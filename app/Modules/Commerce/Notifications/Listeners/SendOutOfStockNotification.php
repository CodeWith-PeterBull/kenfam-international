<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications\Listeners;

use App\Modules\Commerce\Inventory\Events\StockDepleted;
use App\Modules\Commerce\Notifications\OperationalNotificationDispatcher;
use App\Modules\Commerce\Notifications\OutOfStockNotification;
use App\Modules\Commerce\Support\CommercePermission;

/** Routes a stock-depleted transition to inventory managers. */
final readonly class SendOutOfStockNotification
{
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    public function handle(StockDepleted $event): void
    {
        $this->notifications->toStaff(
            eventKey: 'out_of_stock',
            permission: CommercePermission::MANAGE_INVENTORY,
            subjectUlid: $event->productUlid,
            notification: new OutOfStockNotification($event->productUlid, $event->balanceAfter),
        );
    }
}
