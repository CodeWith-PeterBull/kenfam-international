<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Notifications;

use App\Models\User;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Throwable;

/** Shared queue policy and final-delivery guards for Property Booking mail. */
abstract class QueuedPropertyBookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected const EVENT_KEY = '';

    public int $tries = 3;

    /** Configure after-commit delivery on the module-owned queue. */
    public function __construct()
    {
        $this->afterCommit();

        $queue = trim((string) config('property-booking.notifications.queue', 'notifications'));
        $this->onQueue($queue !== '' ? $queue : 'notifications');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /** Determine whether the module and this notification type remain enabled. */
    protected function eventEnabled(): bool
    {
        return (bool) config('property-booking.enabled', true)
            && (bool) config('property-booking.notifications.enabled', true)
            && static::EVENT_KEY !== ''
            && (bool) config('property-booking.notifications.events.'.static::EVENT_KEY, true);
    }

    /** Revalidate staff state, capability, and property scope at delivery time. */
    protected function permittedStaff(object $notifiable, string $permission, int $propertyId): bool
    {
        if (! $this->eventEnabled()
            || ! $notifiable instanceof User
            || ! $notifiable->is_active
            || $notifiable->email_verified_at === null) {
            return false;
        }

        try {
            $permitted = $notifiable->isSystemAdministrator() || $notifiable->hasPermissionTo($permission);

            return $permitted && app(PropertyAccessService::class)->canAccess($notifiable, $propertyId);
        } catch (Throwable) {
            return false;
        }
    }

    /** Match an anonymous route to the immutable guest email snapshot. */
    protected function matchesBookingEmail(object $notifiable, ?string $email): bool
    {
        if (! $this->eventEnabled() || ! $notifiable instanceof AnonymousNotifiable) {
            return false;
        }

        $email = strtolower(trim((string) $email));
        $route = strtolower(trim((string) $notifiable->routeNotificationFor('mail')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && hash_equals($email, $route);
    }
}
