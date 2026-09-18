<?php

/** Shared queue policy for every TravelTours mail notification. */

declare(strict_types=1);

namespace App\Modules\TravelTours\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Listeners run synchronously inside the request and hand off to one queued
 * notification, so each domain event produces exactly one queue job. The job
 * is released only after the surrounding transaction commits, runs on the
 * module's configured queue, and retries a few times with growing delays.
 */
abstract class QueuedTravelNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    /** Apply the module queue policy; subclasses call this from their constructors. */
    protected function applyQueuePolicy(): void
    {
        $this->afterCommit();
        $this->onQueue((string) config('travel-tours.notifications.queue', 'notifications'));
    }

    /**
     * Mail is the only channel the module delivers today.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }
}
