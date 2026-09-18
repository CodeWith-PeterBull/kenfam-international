<?php

/**
 * Runs one assisted sale from quote to receipt inside a single transaction.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Services;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Data\BookingPaymentData;
use App\Modules\TravelTours\Bookings\Data\BookingPlacementData;
use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Bookings\Services\BookingLifecycleService;
use App\Modules\TravelTours\Contracts\CalculatesTourQuotes;
use App\Modules\TravelTours\Contracts\PlacesTourBookings;
use App\Modules\TravelTours\Contracts\ProcessesBookingPayments;
use App\Modules\TravelTours\PointOfBooking\Data\DeskSaleData;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\Pricing\Data\TourQuoteRequest;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Data\AvailabilityHoldRequest;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Scheduling\Services\AvailabilityHoldService;
use Illuminate\Database\DatabaseManager;

/**
 * The desk reuses the storefront's services rather than its own path: the
 * quote, hold, placement, and payment are the same objects a web customer
 * produces, only attributed to the register, shift, and operator. Money
 * handed over at the desk is recorded and confirmed in one step, and when
 * the confirmed money covers the deposit the booking is confirmed on the
 * operator's authority.
 */
final readonly class DeskCheckoutService
{
    /** Inject the shared domain services; nothing here writes rows directly. */
    public function __construct(
        private DatabaseManager $database,
        private CalculatesTourQuotes $quotes,
        private AvailabilityHoldService $holds,
        private PlacesTourBookings $bookings,
        private ProcessesBookingPayments $payments,
        private BookingLifecycleService $lifecycle,
    ) {}

    /** Complete one sale for the operator's open shift. */
    public function sell(BookingShift $shift, User $operator, DeskSaleData $data): TourBooking
    {
        return $this->database->transaction(function () use ($shift, $operator, $data): TourBooking {
            $shift = BookingShift::query()->lockForUpdate()->findOrFail($shift->getKey());
            if ($shift->status !== ShiftStatus::Open || $shift->operator_id !== $operator->getKey()) {
                throw new PointOfBookingException('An open shift of your own is required to take a booking.');
            }

            $departure = TourDeparture::query()->with('tour')->findOrFail($data->departureId);
            $plan = $this->planFor($departure);
            $quote = $this->quotes->calculate(new TourQuoteRequest($departure->getKey(), $plan->getKey(), $data->mix, $data->promotionCode));
            $ownerToken = 'desk:'.$shift->ulid.':'.$data->operationKey;
            $hold = $this->holds->create(new AvailabilityHoldRequest('desk-hold:'.$data->operationKey, $quote, $ownerToken));

            $booking = $this->bookings->place(new BookingPlacementData(
                operationKey: 'desk:'.$data->operationKey,
                holdUlid: $hold->ulid,
                holdOwnerToken: $ownerToken,
                customer: $data->customer,
                participants: $data->participants,
                channel: BookingChannel::BookingDesk,
                preferredPaymentMethod: $data->preferredPaymentMethod,
                termsVersion: $departure->tour->policy_version ?? 'tour-terms',
                specialRequests: $data->specialRequests,
                attribution: ['source' => 'booking_desk', 'register_id' => $shift->register_id],
                actorId: $operator->getKey(),
                registerId: $shift->register_id,
                shiftId: $shift->getKey(),
            ));

            if ($data->amountReceivedMinor > 0 && $data->paymentMethod !== null) {
                $this->payments->record($booking, new BookingPaymentData(
                    operationKey: 'desk-payment:'.$data->operationKey,
                    method: $data->paymentMethod,
                    amountMinor: $data->amountReceivedMinor,
                    currency: $booking->currency,
                    reference: $data->paymentReference,
                    shiftId: $shift->getKey(),
                    actorId: $operator->getKey(),
                ));
                $booking->refresh();
                if ($booking->status === BookingStatus::Pending && $booking->paid_minor >= $booking->deposit_required_minor) {
                    $this->lifecycle->confirm($booking, $operator->getKey(), 'booking_desk', 'Deposit received at the booking desk.');
                }
            }

            return $booking->fresh(['customer', 'participants', 'payments', 'departure.tour']);
        });
    }

    /** Resolve the plan that prices a departure at the desk: its own active plan, else the tour default. */
    private function planFor(TourDeparture $departure): TourRatePlan
    {
        $assigned = $departure->ratePlan;
        if ($assigned instanceof TourRatePlan && $assigned->is_active) {
            return $assigned;
        }
        $plan = TourRatePlan::query()->where('tour_id', $departure->tour_id)->where('is_active', true)->orderByDesc('is_default')->orderBy('display_order')->first();
        if (! $plan instanceof TourRatePlan) {
            throw new PointOfBookingException('This departure has no active rate plan to price it.');
        }

        return $plan;
    }
}
