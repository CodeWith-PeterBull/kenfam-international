<?php

/**
 * Atomic TravelTours booking placement and immutable snapshot persistence.
 */
declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Services;

use App\Modules\TravelTours\Bookings\Data\BookingParticipantData;
use App\Modules\TravelTours\Bookings\Data\BookingPlacementData;
use App\Modules\TravelTours\Bookings\Enums\BookingMode;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\Bookings\Enums\ParticipantType;
use App\Modules\TravelTours\Bookings\Enums\PaymentStatus;
use App\Modules\TravelTours\Bookings\Exceptions\AvailabilityException;
use App\Modules\TravelTours\Bookings\Exceptions\DuplicateOperationConflict;
use App\Modules\TravelTours\Bookings\Models\BookingStatusHistory;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Contracts\CalculatesTourQuotes;
use App\Modules\TravelTours\Contracts\PlacesTourBookings;
use App\Modules\TravelTours\Customers\Models\TravelCustomer;
use App\Modules\TravelTours\Customers\Services\TravelCustomerService;
use App\Modules\TravelTours\Events\TourBookingPlaced;
use App\Modules\TravelTours\Pricing\Data\ParticipantMix;
use App\Modules\TravelTours\Pricing\Data\TourQuote;
use App\Modules\TravelTours\Pricing\Data\TourQuoteLine;
use App\Modules\TravelTours\Pricing\Data\TourQuoteRequest;
use App\Modules\TravelTours\Pricing\Models\Promotion;
use App\Modules\TravelTours\Pricing\Models\PromotionRedemption;
use App\Modules\TravelTours\Pricing\Models\TourRatePlan;
use App\Modules\TravelTours\Scheduling\Enums\HoldStatus;
use App\Modules\TravelTours\Scheduling\Models\AvailabilityHold;
use App\Modules\TravelTours\Scheduling\Models\TourDeparture;
use App\Modules\TravelTours\Scheduling\Services\AvailabilityHoldService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Place a quote-verified booking under the documented departure-first lock order. */
final readonly class TourBookingService implements PlacesTourBookings
{
    /** Inject only domain collaborators; presentation and authentication stay outside. */
    public function __construct(
        private CalculatesTourQuotes $quotes,
        private TravelCustomerService $customers,
        private AvailabilityHoldService $holds,
        private BookingNumberGenerator $numbers,
    ) {}

    /**
     * Persist one booking, its snapshots, history, and optional promotion redemption.
     *
     * Retries with the same operation key return the original booking only when
     * they identify the same availability hold.
     */
    public function place(BookingPlacementData $data): TourBooking
    {
        return DB::transaction(function () use ($data): TourBooking {
            $existing = TourBooking::query()->where('operation_key', $data->operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->availabilityHold?->ulid !== $data->holdUlid) {
                    throw new DuplicateOperationConflict('The booking operation key was already used with different hold data.');
                }

                return $this->loadPlacementResult($existing);
            }

            $holdReference = AvailabilityHold::query()->where('ulid', $data->holdUlid)->firstOrFail();
            $departure = TourDeparture::query()->with('tour')->lockForUpdate()->findOrFail($holdReference->departure_id);
            $hold = AvailabilityHold::query()->lockForUpdate()->findOrFail($holdReference->id);

            if ($hold->departure_id !== $departure->id) {
                throw new AvailabilityException('The availability hold no longer belongs to the selected departure.');
            }
            if ($hold->status !== HoldStatus::Active || $hold->expires_at->isPast()) {
                throw new AvailabilityException('The booking hold has expired. Please request a new quote.');
            }

            $customer = $this->customers->resolve($data->customer, $data->actorId);
            $this->holds->assertOwnedBy($hold, $data->holdOwnerToken, $customer->id);
            $this->assertParticipantMix($data->participants, $hold);

            $quote = $this->recalculateQuote($hold, $departure);
            if (! hash_equals($hold->quote_fingerprint, $quote->fingerprint)) {
                throw new AvailabilityException('Pricing changed while the booking was held. Please review a refreshed quote.');
            }

            if ($departure->tour->policy_version !== null
                && ! hash_equals($departure->tour->policy_version, $data->termsVersion)) {
                throw new AvailabilityException('Booking terms changed while the quote was open. Please review the current terms.');
            }

            $ratePlan = TourRatePlan::query()->lockForUpdate()->findOrFail($quote->ratePlanId);
            $mode = $departure->booking_mode ?? $departure->tour->booking_mode;
            $status = $mode === BookingMode::Instant ? BookingStatus::Confirmed : BookingStatus::Pending;
            $booking = $this->persistBooking($data, $hold, $departure, $customer, $ratePlan, $quote, $mode, $status);

            $this->persistParticipants($booking, $data->participants, $departure, $quote);
            $this->persistPriceLines($booking, $quote);
            $this->persistInitialHistory($booking, $data, $status);
            $this->persistPromotionRedemption($booking, $quote);

            $hold->forceFill([
                'customer_id' => $customer->id,
                'status' => HoldStatus::Consumed,
                'consumed_at' => now(),
            ])->save();

            TourBookingPlaced::dispatch($booking);

            return $this->loadPlacementResult($booking);
        }, 3);
    }

    /** Recalculate a hold from server-owned quote inputs. */
    private function recalculateQuote(AvailabilityHold $hold, TourDeparture $departure): TourQuote
    {
        $snapshot = $hold->quote_snapshot;
        $promotionCode = isset($snapshot['promotion_id'])
            ? Promotion::query()->find((int) $snapshot['promotion_id'])?->code
            : null;

        return $this->quotes->calculate(new TourQuoteRequest(
            departureId: $departure->id,
            ratePlanId: (int) $snapshot['rate_plan_id'],
            participants: new ParticipantMix(
                (int) $hold->adult_count,
                (int) $hold->child_count,
                (int) $hold->infant_count,
            ),
            promotionCode: $promotionCode,
            customerId: $hold->customer_id,
        ));
    }

    /**
     * Verify typed participant rows reproduce the exact held participant mix.
     *
     * @param  list<BookingParticipantData>  $participants
     */
    private function assertParticipantMix(array $participants, AvailabilityHold $hold): void
    {
        $counts = ['adult' => 0, 'child' => 0, 'infant' => 0];
        $leadCount = 0;
        foreach ($participants as $participant) {
            $counts[$participant->type->value]++;
            $leadCount += $participant->isLead ? 1 : 0;
        }

        if ($counts !== [
            'adult' => (int) $hold->adult_count,
            'child' => (int) $hold->child_count,
            'infant' => (int) $hold->infant_count,
        ]) {
            throw new AvailabilityException('Participant details do not match the held quote.');
        }
        if ($leadCount > 1) {
            throw new AvailabilityException('A booking can have only one lead participant.');
        }
    }

    /** Persist the aggregate and all mandatory commercial snapshots. */
    private function persistBooking(
        BookingPlacementData $data,
        AvailabilityHold $hold,
        TourDeparture $departure,
        TravelCustomer $customer,
        TourRatePlan $ratePlan,
        TourQuote $quote,
        BookingMode $mode,
        BookingStatus $status,
    ): TourBooking {
        $booking = new TourBooking;
        $booking->forceFill([
            'booking_number' => $this->numbers->next($data->channel),
            'operation_key' => $data->operationKey,
            'tour_id' => $departure->tour_id,
            'departure_id' => $departure->id,
            'customer_id' => $customer->id,
            'availability_hold_id' => $hold->id,
            'rate_plan_id' => $quote->ratePlanId,
            'promotion_id' => $quote->promotionId,
            'register_id' => $data->registerId,
            'shift_id' => $data->shiftId,
            'agent_id' => $data->actorId,
            'channel' => $data->channel,
            'confirmation_mode' => $mode,
            'status' => $status,
            'payment_status' => PaymentStatus::Unpaid,
            'adult_count' => $hold->adult_count,
            'child_count' => $hold->child_count,
            'infant_count' => $hold->infant_count,
            'seat_count' => $hold->seat_count,
            'currency' => $quote->currency,
            'currency_exponent' => (int) config('travel-tours.defaults.currency_decimals', 2),
            'subtotal_minor' => $quote->subtotalMinor,
            'extras_total_minor' => 0,
            'discount_total_minor' => $quote->discountMinor,
            'tax_total_minor' => $quote->taxMinor,
            'total_minor' => $quote->totalMinor,
            'deposit_required_minor' => $quote->depositMinor,
            'paid_minor' => 0,
            'refunded_minor' => 0,
            'preferred_payment_method' => $data->preferredPaymentMethod,
            'customer_name_snapshot' => trim($data->customer->firstName.' '.$data->customer->lastName),
            'customer_email_snapshot' => $data->customer->email,
            'customer_phone_snapshot' => $data->customer->phone,
            'tour_name_snapshot' => $departure->tour->name,
            'tour_code_snapshot' => $departure->tour->code,
            'departure_timezone_snapshot' => $departure->timezone,
            'departure_starts_at_snapshot' => $departure->starts_at,
            'departure_ends_at_snapshot' => $departure->ends_at,
            'cancellation_terms_snapshot' => $departure->tour->cancellation_summary,
            'policy_version_snapshot' => $departure->tour->policy_version ?? $data->termsVersion,
            'meeting_point_snapshot' => [
                'name' => $departure->tour->meeting_point_name,
                'details' => $departure->meeting_instructions ?? $departure->tour->meeting_point_details,
                'latitude' => $departure->tour->meeting_latitude,
                'longitude' => $departure->tour->meeting_longitude,
            ],
            'rate_plan_snapshot' => [
                'code' => $ratePlan->code,
                'name' => $ratePlan->name,
                'currency' => $ratePlan->currency,
                'tax_inclusive' => (bool) $ratePlan->tax_inclusive,
                'tax_rate_basis_points' => (int) $ratePlan->tax_rate_basis_points,
                'deposit_type' => $ratePlan->deposit_type,
                'deposit_value' => (int) $ratePlan->deposit_value,
                'is_refundable' => (bool) $ratePlan->is_refundable,
                'booking_restrictions' => $ratePlan->booking_restrictions,
            ],
            'pricing_snapshot' => $quote->snapshot(),
            'special_requests' => $data->specialRequests,
            'attribution' => $data->attribution,
            'terms_accepted_at' => now(),
            'terms_version' => $data->termsVersion,
            'placed_at' => now(),
            'pending_expires_at' => $status === BookingStatus::Pending
                ? now()->addMinutes((int) config('travel-tours.booking.pending_minutes', 1440))
                : null,
            'confirmed_at' => $status === BookingStatus::Confirmed ? now() : null,
            'created_by' => $data->actorId,
            'updated_by' => $data->actorId,
        ]);
        $booking->save();

        return $booking;
    }

    /**
     * Persist ordered participant snapshots with deterministic fare allocation.
     *
     * @param  list<BookingParticipantData>  $participants
     */
    private function persistParticipants(
        TourBooking $booking,
        array $participants,
        TourDeparture $departure,
        TourQuote $quote,
    ): void {
        $allocations = $this->participantAllocations($participants, $quote);
        $departureDate = CarbonImmutable::instance($departure->starts_at)->setTimezone($departure->timezone)->startOfDay();

        foreach ($participants as $index => $participant) {
            $booking->participants()->create($participant->snapshot(
                sequence: $index + 1,
                departureDate: $departureDate,
                allocatedPriceMinor: $allocations[$index],
            ));
        }
    }

    /**
     * Allocate each fare line exactly, distributing integer remainders by order.
     *
     * @param  list<BookingParticipantData>  $participants
     * @return list<int>
     */
    private function participantAllocations(array $participants, TourQuote $quote): array
    {
        $allocations = array_fill(0, count($participants), 0);
        foreach (ParticipantType::cases() as $type) {
            $indexes = array_keys(array_filter(
                $participants,
                static fn (BookingParticipantData $participant): bool => $participant->type === $type,
            ));
            if ($indexes === []) {
                continue;
            }

            $line = collect($quote->lines)->first(
                static fn (TourQuoteLine $candidate): bool => $candidate->type === 'fare'
                    && ($candidate->metadata['participant_type'] ?? null) === $type->value,
            );
            $total = $line instanceof TourQuoteLine ? $line->totalMinor : 0;
            $unit = intdiv($total, count($indexes));
            $remainder = $total - ($unit * count($indexes));
            foreach ($indexes as $position => $index) {
                $allocations[$index] = $unit + ($position < $remainder ? 1 : 0);
            }
        }

        return $allocations;
    }

    /** Persist immutable quote lines in stable document order. */
    private function persistPriceLines(TourBooking $booking, TourQuote $quote): void
    {
        foreach ($quote->lines as $index => $line) {
            $booking->priceLines()->create([
                'pricing_rule_id' => $line->metadata['pricing_rule_id'] ?? null,
                'promotion_id' => $line->metadata['promotion_id'] ?? null,
                'line_type' => $line->type,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_amount_minor' => $line->unitAmountMinor,
                'discount_minor' => $line->totalMinor < 0 ? abs($line->totalMinor) : 0,
                'tax_minor' => 0,
                'total_minor' => $line->totalMinor,
                'calculation_metadata' => $line->metadata,
                'display_order' => $index,
            ]);
        }
    }

    /** Persist the first append-only lifecycle transition. */
    private function persistInitialHistory(
        TourBooking $booking,
        BookingPlacementData $data,
        BookingStatus $status,
    ): void {
        $history = new BookingStatusHistory;
        $history->forceFill([
            'booking_id' => $booking->id,
            'previous_status' => null,
            'new_status' => $status,
            'actor_id' => $data->actorId,
            'source' => $data->channel->value,
            'reason' => 'Booking placed.',
            'changed_at' => now(),
        ])->save();
    }

    /** Lock promotion usage limits and persist immutable redemption evidence. */
    private function persistPromotionRedemption(TourBooking $booking, TourQuote $quote): void
    {
        if ($quote->promotionId === null || $quote->discountMinor === 0) {
            return;
        }

        $promotion = Promotion::query()->lockForUpdate()->findOrFail($quote->promotionId);
        $activeRedemptions = $promotion->redemptions()->whereNull('released_at');
        if ($promotion->maximum_uses !== null && (clone $activeRedemptions)->count() >= $promotion->maximum_uses) {
            throw new AvailabilityException('The selected promotion has reached its usage limit.');
        }
        if ($promotion->maximum_uses_per_customer !== null
            && (clone $activeRedemptions)->where('customer_id', $booking->customer_id)->count() >= $promotion->maximum_uses_per_customer) {
            throw new AvailabilityException('The selected promotion has reached its customer usage limit.');
        }

        PromotionRedemption::query()->create([
            'promotion_id' => $promotion->id,
            'booking_id' => $booking->id,
            'customer_id' => $booking->customer_id,
            'amount_applied_minor' => $quote->discountMinor,
            'currency' => $quote->currency,
            'redeemed_at' => now(),
        ]);
    }

    /** Load only the aggregate relations expected immediately after placement. */
    private function loadPlacementResult(TourBooking $booking): TourBooking
    {
        return $booking->loadMissing([
            'customer',
            'departure.tour',
            'participants',
            'priceLines',
            'promotionRedemption',
        ]);
    }
}
