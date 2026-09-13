<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications;

use Illuminate\Notifications\Notification as LaravelNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/** Queues operational mail without turning a committed mutation into an HTTP failure. */
final readonly class OperationalNotificationDispatcher
{
    public function __construct(private CommerceNotificationRecipientResolver $recipients) {}

    public function toStaff(
        string $eventKey,
        string $permission,
        string $subjectUlid,
        LaravelNotification $notification,
    ): void {
        if (! $this->enabled($eventKey)) {
            return;
        }

        $this->safely($eventKey, $subjectUlid, function () use ($permission, $notification): void {
            $recipients = $this->recipients->staffWithPermission($permission);
            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, $notification);
            }
        });
    }

    public function toOrderCustomer(
        string $eventKey,
        string $orderUlid,
        LaravelNotification $notification,
    ): void {
        if (! $this->enabled($eventKey)) {
            return;
        }

        $this->safely($eventKey, $orderUlid, function () use ($orderUlid, $notification): void {
            $email = $this->recipients->orderSnapshotEmail($orderUlid);
            if ($email !== null) {
                Notification::route('mail', $email)->notify($notification);
            }
        });
    }

    private function enabled(string $eventKey): bool
    {
        return (bool) config('commerce.enabled', true)
            && (bool) config('commerce.notifications.enabled', true)
            && (bool) config("commerce.notifications.events.{$eventKey}", true);
    }

    private function safely(string $eventKey, string $subjectUlid, callable $send): void
    {
        try {
            $send();
        } catch (Throwable $exception) {
            Log::error('Commerce operational notification could not be queued.', [
                'event' => $eventKey,
                'subject_ulid' => $subjectUlid,
                'exception' => $exception::class,
            ]);

            report($exception);
        }
    }
}
