<?php

/**
 * Deterministic allocation of confirmed booking payments to instalments.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Services;

use App\Modules\TravelTours\Bookings\Enums\PaymentScheduleStatus;
use App\Modules\TravelTours\Bookings\Exceptions\PaymentException;
use App\Modules\TravelTours\Bookings\Models\PaymentSchedule;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use Illuminate\Database\Eloquent\Collection;

/** Allocate integer minor units without losing or duplicating a payment amount. */
final class PaymentScheduleService
{
    /**
     * Allocate a confirmed amount to one requested instalment and then due order.
     *
     * Call this method inside the booking payment transaction after locking the
     * booking. It locks all schedules before updating any row.
     */
    public function allocate(TourBooking $booking, int $amountMinor, ?int $preferredScheduleId = null): void
    {
        if ($amountMinor <= 0) {
            throw new PaymentException('Payment schedule allocation requires a positive amount.');
        }

        /** @var Collection<int, PaymentSchedule> $schedules */
        $schedules = $booking->paymentSchedules()->orderBy('instalment_number')->lockForUpdate()->get();
        if ($schedules->isEmpty()) {
            return;
        }
        if ($preferredScheduleId !== null && ! $schedules->contains('id', $preferredScheduleId)) {
            throw new PaymentException('The selected payment schedule does not belong to this booking.');
        }

        $ordered = $preferredScheduleId === null
            ? $schedules
            : $schedules->sortBy(fn (PaymentSchedule $schedule): int => $schedule->id === $preferredScheduleId ? 0 : $schedule->instalment_number + 1)->values();

        $remaining = $amountMinor;
        foreach ($ordered as $schedule) {
            $outstanding = max((int) $schedule->expected_amount_minor - (int) $schedule->paid_amount_minor, 0);
            if ($outstanding === 0) {
                continue;
            }

            $allocated = min($remaining, $outstanding);
            $paid = (int) $schedule->paid_amount_minor + $allocated;
            $schedule->forceFill([
                'paid_amount_minor' => $paid,
                'status' => $paid >= $schedule->expected_amount_minor
                    ? PaymentScheduleStatus::Paid
                    : PaymentScheduleStatus::Partial,
            ])->save();
            $remaining -= $allocated;

            if ($remaining === 0) {
                return;
            }
        }
    }
}
