<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Services\BookingService;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\PointOfBooking\Data\PobTenderData;
use App\Modules\PropertyBooking\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Pricing\Data\BookingQuote;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use Illuminate\Database\DatabaseManager;

/** Atomically coordinates POB allocation, hold, split tender, and completion. */
final readonly class PobCheckoutService
{
    /** Create the orchestrator from module-owned booking and payment services. */
    public function __construct(
        private DatabaseManager $database,
        private BookingService $bookings,
        private BookingPaymentService $payments,
        private RecordsSystemActivity $activities,
    ) {}

    /** Persist an availability-consuming hold without accepting browser totals. */
    public function hold(
        BookingQuote $quote,
        RatePlan $ratePlan,
        Guest $guest,
        ReceptionShift $shift,
        User $receptionist,
        ?string $specialRequests = null,
        ?string $internalNote = null,
    ): Booking {
        return $this->bookings->placePointOfBooking(
            $quote, $ratePlan, $guest, $shift, $receptionist, true, $specialRequests, $internalNote,
        );
    }

    /** Complete a new reception booking under one all-or-nothing transaction. */
    public function checkout(
        BookingQuote $quote,
        RatePlan $ratePlan,
        Guest $guest,
        array $tenders,
        ReceptionShift $shift,
        User $receptionist,
        ?string $specialRequests = null,
        ?string $internalNote = null,
    ): Booking {
        $this->assertTenders($tenders, $quote->calculation->totalMinor);

        return $this->database->transaction(function () use ($quote, $ratePlan, $guest, $tenders, $shift, $receptionist, $specialRequests, $internalNote): Booking {
            $booking = $this->bookings->placePointOfBooking(
                $quote, $ratePlan, $guest, $shift, $receptionist, false, $specialRequests, $internalNote,
            );
            $this->settle($booking, $tenders, $shift, $receptionist);

            return $this->completed($booking, $shift, $receptionist);
        });
    }

    /** Reprice and settle an existing owned hold atomically. */
    public function checkoutHeld(
        Booking $heldBooking,
        array $tenders,
        ReceptionShift $shift,
        User $receptionist,
    ): Booking {
        return $this->database->transaction(function () use ($heldBooking, $tenders, $shift, $receptionist): Booking {
            $booking = $this->bookings->confirmHeldPointOfBooking($heldBooking, $shift, $receptionist);
            $this->assertTenders($tenders, $booking->total_minor);
            $this->settle($booking, $tenders, $shift, $receptionist);

            return $this->completed($booking, $shift, $receptionist);
        });
    }

    /** Discard an owned hold through the booking aggregate service. */
    public function discardHeld(Booking $booking, ReceptionShift $shift, User $receptionist): Booking
    {
        return $this->bookings->discardHeldPointOfBooking($booking, $shift, $receptionist);
    }

    /** @param list<PobTenderData> $tenders */
    private function settle(Booking $booking, array $tenders, ReceptionShift $shift, User $receptionist): void
    {
        foreach ($tenders as $tender) {
            $this->payments->recordCompleted($booking, $tender, $shift, $receptionist);
        }
    }

    /** Finalize the audit projection after exact settlement. */
    private function completed(Booking $booking, ReceptionShift $shift, User $receptionist): Booking
    {
        $booking->refresh();
        if ($booking->payment_status !== BookingPaymentStatus::Paid || $booking->paid_minor !== $booking->total_minor) {
            throw new PointOfBookingException('Point of Booking payments must settle the booking total exactly.');
        }
        $this->activities->record(
            activityType: 'property-booking.pob-booking.completed',
            description: "POB booking {$booking->booking_number} completed",
            actor: $receptionist,
            subject: $booking,
            properties: [
                'booking_ulid' => $booking->ulid,
                'shift_ulid' => $shift->ulid,
                'total_minor' => $booking->total_minor,
                'payment_count' => $booking->payments()->count(),
            ],
            severity: SystemActivitySeverity::Notice,
            source: 'property-booking-pob',
        );

        return $booking->refresh()->load([
            'property', 'primaryGuest', 'stays.unitType', 'stays.ratePlan',
            'unitAssignments.unit', 'payments', 'register', 'receptionShift', 'receptionist.profile',
        ]);
    }

    /** @param array<array-key, mixed> $tenders */
    private function assertTenders(array $tenders, int $totalMinor): void
    {
        $maximum = max(1, min(10, (int) config('property-booking.pob.maximum_tenders', 4)));
        if ($tenders === [] || count($tenders) > $maximum) {
            throw new PointOfBookingException("A booking requires between one and {$maximum} payment tenders.");
        }
        foreach ($tenders as $tender) {
            if (! $tender instanceof PobTenderData) {
                throw new PointOfBookingException('Point of Booking payments must use immutable tender data.');
            }
        }
        if (array_sum(array_map(static fn (PobTenderData $tender): int => $tender->amountMinor, $tenders)) !== $totalMinor) {
            throw new PointOfBookingException('Applied payment amounts must equal the booking total exactly.');
        }
    }
}
