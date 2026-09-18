<?php

/**
 * Declares a stable TravelTours capability boundary for future adapters.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Contracts;

use App\Modules\TravelTours\Bookings\Data\BookingPaymentData;
use App\Modules\TravelTours\Bookings\Data\BookingRefundData;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Bookings\Models\BookingRefund;
use App\Modules\TravelTours\Bookings\Models\TourBooking;

/**
 * Adapter-ready booking payment boundary.
 *
 * Manual settlement and a future gateway share one seam: evidence is recorded
 * as pending (with provider and transaction identifier when known) and then
 * confirmed or rejected. record() is the shorthand for money received in hand.
 */
interface ProcessesBookingPayments
{
    /** Record and immediately confirm the one payment identified by the operation key. */
    public function record(TourBooking $booking, BookingPaymentData $data): BookingPayment;

    /** Record or return pending payment evidence identified by the operation key. */
    public function recordPending(TourBooking $booking, BookingPaymentData $data): BookingPayment;

    /** Confirm pending payment evidence and settle the booking aggregates. */
    public function confirm(BookingPayment $payment, ?int $actorId = null): BookingPayment;

    /** Mark pending payment evidence as failed with the operator's reason. */
    public function reject(BookingPayment $payment, ?int $actorId, string $reason): BookingPayment;

    /** Record or return the processed refund identified by the operation key. */
    public function refund(TourBooking $booking, BookingRefundData $data): BookingRefund;
}
