<?php

/**
 * Coordinates desk holds, split tenders, and completion inside one transaction.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Services;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Data\BookingPaymentData;
use App\Modules\TravelTours\Bookings\Data\BookingPlacementData;
use App\Modules\TravelTours\Bookings\Enums\BookingChannel;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Bookings\Services\BookingLifecycleService;
use App\Modules\TravelTours\Contracts\CalculatesTourQuotes;
use App\Modules\TravelTours\Contracts\PlacesTourBookings;
use App\Modules\TravelTours\Contracts\ProcessesBookingPayments;
use App\Modules\TravelTours\PointOfBooking\Data\DeskSaleData;
use App\Modules\TravelTours\PointOfBooking\Data\DeskTenderData;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\Pricing\Data\TourQuoteRequest;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Data\AvailabilityHoldRequest;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Scheduling\Services\AvailabilityHoldService;
use App\Modules\TravelTours\Support\MoneyFormatter;
use Illuminate\Database\DatabaseManager;

/**
 * The desk reuses the storefront's services rather than its own path: the
 * quote, hold, placement, and payments are the same objects a web customer
 * produces, only attributed to the register, shift, and operator. A hold is
 * a pending booking parked on the shift; completing a sale records every
 * tender as confirmed money, and once the deposit is covered the booking is
 * confirmed on the operator's authority.
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

    /** Park the selection as a pending booking on the shift without taking money. */
    public function hold(BookingShift $shift, User $operator, DeskSaleData $data): TourBooking
    {
        return $this->database->transaction(function () use ($shift, $operator, $data): TourBooking {
            $shift = $this->ownShift($shift, $operator);

            return $this->place($shift, $operator, $data, PaymentMethod::Cash)->fresh(['customer', 'participants', 'departure.tour']);
        });
    }

    /**
     * Complete a new booking with exact split tenders.
     *
     * @param  list<DeskTenderData>  $tenders
     */
    public function checkout(BookingShift $shift, User $operator, DeskSaleData $data, array $tenders): TourBooking
    {
        $this->assertTenderShape($tenders);

        return $this->database->transaction(function () use ($shift, $operator, $data, $tenders): TourBooking {
            $shift = $this->ownShift($shift, $operator);
            $booking = $this->place($shift, $operator, $data, $tenders[0]->method);
            $this->settle($booking, $tenders, $shift, $operator, 'desk-payment:'.$data->operationKey);

            return $this->completed($booking, $operator);
        });
    }

    /**
     * Settle a hold parked on this shift with exact split tenders.
     *
     * @param  list<DeskTenderData>  $tenders
     */
    public function checkoutHeld(TourBooking $held, array $tenders, BookingShift $shift, User $operator, string $operationKey): TourBooking
    {
        $this->assertTenderShape($tenders);

        return $this->database->transaction(function () use ($held, $tenders, $shift, $operator, $operationKey): TourBooking {
            $shift = $this->ownShift($shift, $operator);
            $booking = $this->heldOn($held, $shift);
            $this->settle($booking, $tenders, $shift, $operator, 'desk-settle:'.$operationKey);

            return $this->completed($booking, $operator);
        });
    }

    /** Discard a hold parked on this shift, releasing its seats. */
    public function discardHeld(TourBooking $held, BookingShift $shift, User $operator): TourBooking
    {
        return $this->database->transaction(function () use ($held, $shift, $operator): TourBooking {
            $shift = $this->ownShift($shift, $operator);
            $booking = $this->heldOn($held, $shift);

            return $this->lifecycle->cancel($booking, $operator->getKey(), 'Hold discarded at the booking desk.', 'booking_desk');
        });
    }

    /** Price, hold, and place one desk booking attributed to the shift. */
    private function place(BookingShift $shift, User $operator, DeskSaleData $data, PaymentMethod $preferredMethod): TourBooking
    {
        $departure = TourDeparture::query()->with('tour')->findOrFail($data->departureId);
        $plan = $this->planFor($departure);
        $quote = $this->quotes->calculate(new TourQuoteRequest($departure->getKey(), $plan->getKey(), $data->mix, $data->promotionCode));
        $ownerToken = 'desk:'.$shift->ulid.':'.$data->operationKey;
        $hold = $this->holds->create(new AvailabilityHoldRequest('desk-hold:'.$data->operationKey, $quote, $ownerToken));

        return $this->bookings->place(new BookingPlacementData(
            operationKey: 'desk:'.$data->operationKey,
            holdUlid: $hold->ulid,
            holdOwnerToken: $ownerToken,
            customer: $data->customer,
            participants: $data->participants,
            channel: BookingChannel::BookingDesk,
            preferredPaymentMethod: $preferredMethod,
            termsVersion: $departure->tour->policy_version ?? 'tour-terms',
            specialRequests: $data->specialRequests,
            attribution: ['source' => 'booking_desk', 'register_id' => $shift->register_id],
            actorId: $operator->getKey(),
            registerId: $shift->register_id,
            shiftId: $shift->getKey(),
        ));
    }

    /**
     * Record every tender as confirmed money; the sum must cover the deposit and never exceed the balance.
     *
     * @param  list<DeskTenderData>  $tenders
     */
    private function settle(TourBooking $booking, array $tenders, BookingShift $shift, User $operator, string $keyPrefix): void
    {
        $booking->refresh();
        $applied = array_sum(array_map(static fn (DeskTenderData $tender): int => $tender->amountMinor, $tenders));
        $outstandingDeposit = max((int) $booking->deposit_required_minor - (int) $booking->paid_minor, 0);
        if ($applied < $outstandingDeposit) {
            throw new PointOfBookingException('Take at least the deposit of '.MoneyFormatter::format($outstandingDeposit, $booking->currency, (int) $booking->currency_exponent).' to complete this booking, or hold it instead.');
        }
        if ($applied > $booking->balance_minor) {
            throw new PointOfBookingException('Applied tenders exceed the outstanding balance of '.MoneyFormatter::format((int) $booking->balance_minor, $booking->currency, (int) $booking->currency_exponent).'.');
        }

        foreach ($tenders as $index => $tender) {
            $this->payments->record($booking, new BookingPaymentData(
                operationKey: "{$keyPrefix}:{$index}",
                method: $tender->method,
                amountMinor: $tender->amountMinor,
                currency: $booking->currency,
                reference: $tender->reference,
                shiftId: $shift->getKey(),
                actorId: $operator->getKey(),
                safeMetadata: $tender->tenderedMinor === null ? [] : ['tendered_minor' => $tender->tenderedMinor, 'change_minor' => $tender->changeMinor()],
            ));
        }
    }

    /** Confirm the booking once the desk money has been recorded. */
    private function completed(TourBooking $booking, User $operator): TourBooking
    {
        $booking->refresh();
        if ($booking->status === BookingStatus::Pending) {
            $this->lifecycle->confirm($booking, $operator->getKey(), 'booking_desk', 'Deposit received at the booking desk.');
        }

        return $booking->fresh(['customer', 'participants', 'payments', 'departure.tour', 'register', 'agent']);
    }

    /** Lock the shift and require it to be open and the operator's own. */
    private function ownShift(BookingShift $shift, User $operator): BookingShift
    {
        $shift = BookingShift::query()->lockForUpdate()->findOrFail($shift->getKey());
        if ($shift->status !== ShiftStatus::Open || $shift->operator_id !== $operator->getKey()) {
            throw new PointOfBookingException('An open shift of your own is required to take a booking.');
        }

        return $shift;
    }

    /** Lock a booking and require it to be a hold parked on this shift. */
    private function heldOn(TourBooking $held, BookingShift $shift): TourBooking
    {
        $booking = TourBooking::query()->lockForUpdate()->findOrFail($held->getKey());
        if ($booking->shift_id !== $shift->getKey() || $booking->channel !== BookingChannel::BookingDesk || $booking->status !== BookingStatus::Pending) {
            throw new PointOfBookingException('This hold is no longer open on your shift.');
        }

        return $booking;
    }

    /**
     * Refuse malformed tender lists before any row is touched.
     *
     * @param  array<array-key, mixed>  $tenders
     */
    private function assertTenderShape(array $tenders): void
    {
        $maximum = max(1, min(10, (int) config('travel-tours.pob.maximum_tenders', 4)));
        if ($tenders === [] || count($tenders) > $maximum) {
            throw new PointOfBookingException("A booking takes between one and {$maximum} payment tenders.");
        }
        foreach ($tenders as $tender) {
            if (! $tender instanceof DeskTenderData) {
                throw new PointOfBookingException('Desk payments must use typed tender data.');
            }
        }
    }

    /** Resolve the plan that prices a departure at the desk: its own active plan, else the tour default. */
    public function planFor(TourDeparture $departure): TourRatePlan
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
