<?php

declare(strict_types=1);

namespace App\Modules\Commerce\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Notification;
use Throwable;

/** Shared queue and final-delivery guards for dedicated Commerce mail. */
abstract class QueuedCommerceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected const EVENT_KEY = '';

    public int $tries = 3;

    public function __construct()
    {
        $this->afterCommit();

        $queue = trim((string) config('commerce.notifications.queue', 'default'));
        if ($queue !== '') {
            $this->onQueue($queue);
        }
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

    protected function eventEnabled(): bool
    {
        return (bool) config('commerce.enabled', true)
            && (bool) config('commerce.notifications.enabled', true)
            && static::EVENT_KEY !== ''
            && (bool) config('commerce.notifications.events.'.static::EVENT_KEY, true);
    }

    protected function permittedStaff(object $notifiable, string $permission): bool
    {
        if (! $this->eventEnabled() || ! $notifiable instanceof User || ! $notifiable->is_active) {
            return false;
        }

        try {
            return $notifiable->hasPermissionTo($permission);
        } catch (Throwable) {
            return false;
        }
    }

    protected function matchesOrderEmail(object $notifiable, ?string $email): bool
    {
        if (! $this->eventEnabled() || ! $notifiable instanceof AnonymousNotifiable) {
            return false;
        }

        $email = strtolower(trim((string) $email));
        $route = strtolower(trim((string) $notifiable->routeNotificationFor('mail')));

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && hash_equals($email, $route);
    }
}
