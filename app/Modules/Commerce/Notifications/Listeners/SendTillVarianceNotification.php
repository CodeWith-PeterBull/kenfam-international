<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications\Listeners;

use App\Modules\Commerce\Notifications\OperationalNotificationDispatcher;
use App\Modules\Commerce\Notifications\TillVarianceNotification;
use App\Modules\Commerce\PointOfSale\Events\TillVarianceDetected;
use App\Modules\Commerce\Support\CommercePermission;

/** Routes material closed-till variance alerts to till managers. */
final readonly class SendTillVarianceNotification
{
    public function __construct(private OperationalNotificationDispatcher $notifications) {}

    public function handle(TillVarianceDetected $event): void
    {
        $this->notifications->toStaff(
            eventKey: 'till_variance',
            permission: CommercePermission::MANAGE_TILLS,
            subjectUlid: $event->tillSessionUlid,
            notification: new TillVarianceNotification(
                $event->tillSessionUlid,
                $event->varianceMinor,
                $event->thresholdMinor,
            ),
        );
    }
}
