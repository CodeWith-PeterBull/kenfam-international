<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Notifications;

use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Queues post-commit mail without turning committed operations into failures. */
final readonly class OperationalNotificationDispatcher
{
    /** Create the dispatcher with its scoped recipient resolver. */
    public function __construct(private PropertyBookingNotificationRecipientResolver $recipients) {}

    /** Queue one event-specific message for authorized property staff. */
    public function toStaff(
        string $eventKey,
        int $propertyId,
        string $permission,
        string $subjectUlid,
        LaravelNotification $notification,
    ): void {
        if (! $this->enabled($eventKey)) {
            return;
        }

        $this->safely($eventKey, $subjectUlid, function () use ($propertyId, $permission, $notification): void {
            $recipients = $this->recipients->staffWithPermission($propertyId, $permission);
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, $notification);
            }
        });
    }

    /** Queue one event-specific message to the immutable guest email snapshot. */
    public function toBookingCustomer(
        string $eventKey,
        string $bookingUlid,
        LaravelNotification $notification,
    ): void {
        if (! $this->enabled($eventKey)) {
            return;
        }

        $this->safely($eventKey, $bookingUlid, function () use ($bookingUlid, $notification): void {
            $email = $this->recipients->bookingSnapshotEmail($bookingUlid);
            if ($email !== null) {
                Notification::route('mail', $email)->notify($notification);
            }
        });
    }

    /** Determine whether one event family is enabled at dispatch time. */
    private function enabled(string $eventKey): bool
    {
        return (bool) config('property-booking.enabled', true)
            && (bool) config('property-booking.notifications.enabled', true)
            && (bool) config("property-booking.notifications.events.{$eventKey}", true);
    }

    /** Isolate notification infrastructure failures from committed domain writes. */
    private function safely(string $eventKey, string $subjectUlid, callable $send): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            Log::error('Property Booking operational notification could not be queued.', [
                'event' => $eventKey,
                'subject_ulid' => $subjectUlid,
                'exception' => $exception::class,
            ]);

            report($exception);
        }
    }
}
