<?php

/**
 * Owns booking lifecycle transitions after placement.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Services;

use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Exceptions\BookingLifecycleException;
use App\Modules\TravelTours\Bookings\Models\BookingStatusHistory;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Database\DatabaseManager;

/**
 * Every transition is written under the booking's row lock with an append-only
 * history row. Capacity is released implicitly: only Held, Pending, and
 * Confirmed bookings count against a departure, so leaving those states frees
 * the seats without touching departure rows. Confirmation is an operator
 * decision (hybrid mode); payment state is tracked separately.
 */
final readonly class BookingLifecycleService
{
    /** Create the service with its transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /**
     * Expire pending bookings whose approval window has closed without any recorded payment.
     *
     * Each booking is expired in its own transaction so one contended row never
     * blocks the sweep; the count of expired bookings is returned for the log.
     */
    public function expirePending(): int
    {
        $ids = TourBooking::query()
            ->where('status', BookingStatus::Pending->value)
            ->whereNotNull('pending_expires_at')
            ->where('pending_expires_at', '<=', now())
            ->pluck('id');

        $expired = 0;
        foreach ($ids as $id) {
            $expired += $this->database->transaction(function () use ($id): int {
                $booking = TourBooking::query()->lockForUpdate()->find($id);
                if (! $booking instanceof TourBooking || $booking->status !== BookingStatus::Pending) {
                    return 0;
                }
                if ($booking->paid_minor > 0 || $booking->payments()->exists()) {
                    return 0;
                }

                $this->transition($booking, BookingStatus::Expired, null, 'scheduler', 'Approval window closed without payment.');

                return 1;
            });
        }

        return $expired;
    }

    /** Confirm a pending booking on an operator's authority; payment state is tracked separately. */
    public function confirm(TourBooking $booking, ?int $actorId, string $source = 'admin', ?string $reason = null): TourBooking
    {
        return $this->database->transaction(function () use ($booking, $actorId, $source, $reason): TourBooking {
            $locked = TourBooking::query()->lockForUpdate()->findOrFail($booking->getKey());
            if ($locked->status === BookingStatus::Confirmed) {
                return $locked;
            }
            if ($locked->status !== BookingStatus::Pending) {
                throw new BookingLifecycleException('Only pending bookings can be confirmed.');
            }

            $this->transition($locked, BookingStatus::Confirmed, $actorId, $source, $reason ?? 'Confirmed by the travel desk.');

            return $locked->fresh();
        });
    }

    /**
     * Cancel a pending or confirmed booking, freeing its seats and any promotion use.
     *
     * Money already received stays on the booking; refunds are a separate,
     * audited payment-service decision.
     */
    public function cancel(TourBooking $booking, ?int $actorId, string $reason, string $source = 'admin'): TourBooking
    {
        if (trim($reason) === '') {
            throw new BookingLifecycleException('Cancelling a booking requires a reason.');
        }

        return $this->database->transaction(function () use ($booking, $actorId, $reason, $source): TourBooking {
            $locked = TourBooking::query()->lockForUpdate()->findOrFail($booking->getKey());
            if ($locked->status === BookingStatus::Cancelled) {
                return $locked;
            }
            if (! in_array($locked->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)) {
                throw new BookingLifecycleException('Only pending or confirmed bookings can be cancelled.');
            }

            $this->transition($locked, BookingStatus::Cancelled, $actorId, $source, trim($reason));
            $locked->promotionRedemption()->whereNull('released_at')->update(['released_at' => now(), 'release_reason' => 'booking_cancelled']);

            return $locked->fresh();
        });
    }

    /** Mark a confirmed booking as completed once its departure has ended. */
    public function complete(TourBooking $booking, ?int $actorId, string $source = 'admin'): TourBooking
    {
        return $this->database->transaction(function () use ($booking, $actorId, $source): TourBooking {
            $locked = TourBooking::query()->lockForUpdate()->findOrFail($booking->getKey());
            if ($locked->status === BookingStatus::Completed) {
                return $locked;
            }
            if ($locked->status !== BookingStatus::Confirmed) {
                throw new BookingLifecycleException('Only confirmed bookings can be completed.');
            }
            if ($locked->departure_ends_at_snapshot->isFuture()) {
                throw new BookingLifecycleException('A booking can be completed only after its departure has ended.');
            }

            $this->transition($locked, BookingStatus::Completed, $actorId, $source, 'Tour completed.');

            return $locked->fresh();
        });
    }

    /** Write the new status, its timestamp column, and the history row for one locked booking. */
    private function transition(TourBooking $booking, BookingStatus $status, ?int $actorId, string $source, ?string $reason): void
    {
        $previous = $booking->status;
        $booking->forceFill(array_merge([
            'status' => $status,
            'updated_by' => $actorId,
        ], match ($status) {
            BookingStatus::Expired => ['expired_at' => now(), 'pending_expires_at' => null],
            BookingStatus::Cancelled => ['cancelled_at' => now(), 'pending_expires_at' => null],
            BookingStatus::Confirmed => ['confirmed_at' => now(), 'pending_expires_at' => null],
            BookingStatus::Completed => ['completed_at' => now()],
            default => [],
        }))->save();

        $history = new BookingStatusHistory;
        $history->forceFill([
            'booking_id' => $booking->getKey(),
            'previous_status' => $previous,
            'new_status' => $status,
            'actor_id' => $actorId,
            'source' => $source,
            'reason' => $reason,
            'changed_at' => now(),
        ])->save();
    }
}
