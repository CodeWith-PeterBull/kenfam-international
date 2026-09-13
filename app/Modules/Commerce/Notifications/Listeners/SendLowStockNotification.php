<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications\Listeners;

use App\Modules\Commerce\Inventory\Events\StockBecameLow;
use App\Modules\Commerce\Notifications\LowStockNotification;
use App\Modules\Commerce\Notifications\OperationalNotificationDispatcher;
use App\Modules\Commerce\Support\CommercePermission;

/** Routes the first positive low-stock transition to inventory managers. */
final readonly class SendLowStockNotification
{
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    public function handle(StockBecameLow $event): void
    {
        $this->notifications->toStaff(
            eventKey: 'low_stock',
            permission: CommercePermission::MANAGE_INVENTORY,
            subjectUlid: $event->productUlid,
            notification: new LowStockNotification($event->productUlid, $event->balanceAfter, $event->threshold),
        );
    }
}
