<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Availability\Services\UnitAllocationService;
use App\Modules\PropertyBooking\Bookings\Enums\BookingChannel;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Events\BookingConfirmed;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Catalog\Models\Property;
use App\Modules\PropertyBooking\Guests\Models\Guest;
use App\Modules\PropertyBooking\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Pricing\Data\BookingQuote;
use App\Modules\PropertyBooking\Pricing\Enums\AdvanceNoticePolicy;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingRateCalculator;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Illuminate\Database\DatabaseManager;

/** Owns reservation aggregate creation and authoritative POB hold transitions. */
final readonly class BookingService
{
    /** Create the booking service with transactional domain collaborators. */
    public function __construct(
        private DatabaseManager $database,
        private BookingNumberService $numbers,
        private UnitAllocationService $allocation,
        private BookingRateCalculator $rates,
        private RecordsSystemActivity $activities,
    ) {}

    /** Persist one pending web booking and its exact-unit allocation atomically. */
    public function placeWeb(
        BookingQuote $quote,
        RatePlan $ratePlan,
        Guest $guest,
        BookingPaymentMethod $paymentPreference,
        ?string $specialRequests = null,
        ?User $account = null,
    ): Booking {
        return $this->place(
            quote: $quote,
            ratePlan: $ratePlan,
            guest: $guest,
            channel: BookingChannel::Web,
            status: BookingStatus::Pending,
            paymentPreference: $paymentPreference,
            actor: $account,
            specialRequests: $specialRequests,
        );
    }

    /** Create one held or confirmed reception booking under an open shift. */
    public function placePointOfBooking(
        BookingQuote $quote,
        RatePlan $ratePlan,
        Guest $guest,
        ReceptionShift $shift,
        User $receptionist,
        bool $hold,
        ?string $specialRequests = null,
        ?string $internalNote = null,
    ): Booking {
        $this->assertPointOfBookingGuestIdentity($guest);

        return $this->place(
            quote: $quote,
            ratePlan: $ratePlan,
            guest: $guest,
            channel: BookingChannel::PointOfBooking,
            status: $hold ? BookingStatus::Held : BookingStatus::Confirmed,
            paymentPreference: null,
            actor: $receptionist,
            shift: $shift,
            specialRequests: $specialRequests,
            internalNote: $internalNote,
        );
    }

    /** Reprice and confirm an owned, unexpired POB hold without trusting stored totals. */
    public function confirmHeldPointOfBooking(
        Booking $booking,
        ReceptionShift $shift,
        User $receptionist,
    ): Booking {
        return $this->database->transaction(function () use ($booking, $shift, $receptionist): Booking {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $shift = $this->assertPobShift($shift, $receptionist, $booking->property_id);
            if ($booking->channel !== BookingChannel::PointOfBooking
                || $booking->status !== BookingStatus::Held
                || $booking->reception_shift_id !== $shift->getKey()
                || $booking->receptionist_id !== $shift->receptionist_id) {
                throw new PointOfBookingException('The held booking does not belong to the selected reception shift.');
            }
            if ($booking->hold_expires_at === null || $booking->hold_expires_at->isPast()) {
                throw new PointOfBookingException('This booking hold has expired and must be discarded.');
            }
            $booking->loadMissing('primaryGuest');
            if (! $booking->primaryGuest instanceof Guest) {
                throw new PointOfBookingException('The held booking no longer has a valid guest profile.');
            }
            $this->assertPointOfBookingGuestIdentity($booking->primaryGuest);

            $stay = BookingStay::query()->with(['ratePlan.property', 'ratePlan.unitType', 'activeAssignment.unit'])
                ->where('booking_id', $booking->getKey())
                ->lockForUpdate()
                ->sole();
            if ($stay->ratePlan === null || $stay->activeAssignment === null) {
                throw new PointOfBookingException('The held booking no longer has a valid rate or unit allocation.');
            }
            $calculation = $this->rates->calculate(
                $stay->ratePlan,
                $stay->starts_at->toImmutable(),
                $stay->ends_at->toImmutable(),
                $stay->adult_count,
                $stay->child_count,
                $stay->infant_count,
                $this->pointOfBookingAdvanceNoticePolicy(),
            );

            $stay->forceFill([
                'rate_plan_name' => $stay->ratePlan->name,
                'rate_plan_code' => $stay->ratePlan->code,
                'pricing_unit' => $calculation->pricingUnit,
                'billable_units' => $calculation->billableUnits,
                'unit_rate_minor' => $calculation->unitRateMinor,
                'extra_guest_minor' => $calculation->extraGuestMinor,
                'subtotal_minor' => $calculation->subtotalMinor,
                'tax_rate_bps' => $calculation->taxRateBps,
                'tax_minor' => $calculation->taxMinor,
                'total_minor' => $calculation->totalMinor,
                'is_tax_inclusive' => $calculation->taxInclusive,
            ])->save();
            $booking->forceFill([
                'status' => BookingStatus::Confirmed,
                'accommodation_subtotal_minor' => $calculation->subtotalMinor,
                'tax_minor' => $calculation->taxMinor,
                'total_minor' => max(
                    0,
                    $calculation->totalMinor + $booking->charges_subtotal_minor - $booking->discount_minor,
                ),
                'required_deposit_minor' => $calculation->requiredDepositMinor,
                'tax_inclusive' => $calculation->taxInclusive,
                'hold_expires_at' => null,
                'confirmed_at' => now(),
                'updated_by' => $receptionist->getKey(),
            ])->save();

            $this->activities->record(
                activityType: 'property-booking.pob-hold.confirmed',
                description: "POB hold {$booking->booking_number} repriced and confirmed",
                actor: $receptionist,
                subject: $booking,
                properties: ['booking_ulid' => $booking->ulid, 'shift_ulid' => $shift->ulid],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-pob',
            );
            BookingConfirmed::dispatch($booking->ulid, $booking->property_id);

            return $booking->refresh()->load($this->pobRelations());
        });
    }

    /** Expire one owned hold and release its concrete unit allocation. */
    public function discardHeldPointOfBooking(
        Booking $booking,
        ReceptionShift $shift,
        User $receptionist,
    ): Booking {
        return $this->database->transaction(function () use ($booking, $shift, $receptionist): Booking {
            $booking = Booking::query()->with('unitAssignments')->lockForUpdate()->findOrFail($booking->getKey());
            $shift = $this->assertPobShift($shift, $receptionist, $booking->property_id);
            if ($booking->status !== BookingStatus::Held
                || $booking->reception_shift_id !== $shift->getKey()
                || $booking->receptionist_id !== $shift->receptionist_id) {
                throw new PointOfBookingException('Only an owned held booking may be discarded from this shift.');
            }

            foreach ($booking->unitAssignments as $assignment) {
                if ($assignment->status->value === 'active') {
                    $this->allocation->release($assignment, 'POB hold discarded', $receptionist);
                }
            }
            $booking->forceFill([
                'status' => BookingStatus::Expired,
                'hold_expires_at' => null,
                'updated_by' => $receptionist->getKey(),
            ])->save();
            $this->activities->record(
                activityType: 'property-booking.pob-hold.discarded',
                description: "POB hold {$booking->booking_number} discarded",
                actor: $receptionist,
                subject: $booking,
                properties: ['booking_ulid' => $booking->ulid, 'shift_ulid' => $shift->ulid],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-pob',
            );

            return $booking->refresh();
        });
    }

    /** Persist the shared immutable booking and stay snapshots. */
    private function place(
        BookingQuote $quote,
        RatePlan $ratePlan,
        Guest $guest,
        BookingChannel $channel,
        BookingStatus $status,
        ?BookingPaymentMethod $paymentPreference,
        ?User $actor,
        ?ReceptionShift $shift = null,
        ?string $specialRequests = null,
        ?string $internalNote = null,
    ): Booking {
        return $this->database->transaction(function () use ($quote, $ratePlan, $guest, $channel, $status, $paymentPreference, $actor, $shift, $specialRequests, $internalNote): Booking {
            if ($channel === BookingChannel::PointOfBooking) {
                if (! $shift instanceof ReceptionShift || ! $actor instanceof User) {
                    throw new PointOfBookingException('Point of Booking placement requires an operator and reception shift.');
                }
                $shift = $this->assertPobShift($shift, $actor, $quote->propertyId);
            }
            $ratePlan = RatePlan::query()->with(['property', 'unitType'])->lockForUpdate()->findOrFail($ratePlan->getKey());
            if ($ratePlan->getKey() !== $quote->ratePlanId || $ratePlan->property_id !== $quote->propertyId
                || $ratePlan->unit_type_id !== $quote->unitTypeId) {
                throw new PointOfBookingException('The booking quote does not match the selected rate plan.');
            }
            $property = $ratePlan->property;
            $unitType = $ratePlan->unitType;
            $calculation = $this->rates->calculate(
                $ratePlan,
                $quote->startsAt,
                $quote->endsAt,
                $quote->adults,
                $quote->children,
                $quote->infants,
                $channel === BookingChannel::PointOfBooking
                    ? $this->pointOfBookingAdvanceNoticePolicy()
                    : AdvanceNoticePolicy::Enforce,
            );
            $now = now();
            $pendingMinutes = max(0, min(10_080, (int) config('property-booking.booking.pending_minutes', 60)));
            $holdMinutes = max(1, min(1_440, (int) config('property-booking.booking.hold_minutes', 15)));

            $booking = new Booking;
            $booking->forceFill([
                'booking_number' => null,
                'channel' => $channel,
                'property_id' => $property->getKey(),
                'primary_guest_id' => $guest->getKey(),
                'register_id' => $shift?->register_id,
                'reception_shift_id' => $shift?->getKey(),
                'receptionist_id' => $shift?->receptionist_id,
                'created_by' => $actor?->getKey(),
                'updated_by' => $actor?->getKey(),
                'status' => $status,
                'stay_status' => StayStatus::Expected,
                'payment_status' => BookingPaymentStatus::Unpaid,
                'starts_at' => $quote->startsAt,
                'ends_at' => $quote->endsAt,
                'property_timezone' => $property->timezone,
                'adult_count' => $quote->adults,
                'child_count' => $quote->children,
                'infant_count' => $quote->infants,
                'currency' => $calculation->currency,
                'accommodation_subtotal_minor' => $calculation->subtotalMinor,
                'charges_subtotal_minor' => 0,
                'discount_minor' => 0,
                'discount_reason' => null,
                'tax_minor' => $calculation->taxMinor,
                'total_minor' => $calculation->totalMinor,
                'required_deposit_minor' => $calculation->requiredDepositMinor,
                'paid_minor' => 0,
                'tax_inclusive' => $calculation->taxInclusive,
                'property_name' => $property->name,
                'property_code' => $property->code,
                'property_address_summary' => $this->addressSummary($property),
                'guest_first_name' => $guest->first_name,
                'guest_middle_name' => $guest->middle_name,
                'guest_last_name' => $guest->last_name,
                'guest_email' => $guest->email,
                'guest_phone' => $guest->phone,
                'guest_country_code' => $guest->country_code,
                'special_requests' => $this->nullableText($specialRequests),
                'internal_note' => $this->nullableText($internalNote),
                'external_reference' => null,
                'preferred_payment_method' => $paymentPreference,
                'terms_accepted_at' => $channel === BookingChannel::Web ? $now : null,
                'hold_expires_at' => $status === BookingStatus::Held ? $now->copy()->addMinutes($holdMinutes) : null,
                'pending_expires_at' => $status === BookingStatus::Pending && $pendingMinutes > 0 ? $now->copy()->addMinutes($pendingMinutes) : null,
                'placed_at' => $now,
                'confirmed_at' => $status === BookingStatus::Confirmed ? $now : null,
            ])->save();

            $stay = new BookingStay;
            $stay->forceFill([
                'booking_id' => $booking->getKey(),
                'unit_type_id' => $unitType->getKey(),
                'rate_plan_id' => $ratePlan->getKey(),
                'line_number' => 1,
                'starts_at' => $quote->startsAt,
                'ends_at' => $quote->endsAt,
                'pricing_unit' => $calculation->pricingUnit,
                'billable_units' => $calculation->billableUnits,
                'adult_count' => $quote->adults,
                'child_count' => $quote->children,
                'infant_count' => $quote->infants,
                'unit_type_name' => $unitType->name,
                'unit_type_code' => $unitType->code,
                'rate_plan_name' => $ratePlan->name,
                'rate_plan_code' => $ratePlan->code,
                'unit_rate_minor' => $calculation->unitRateMinor,
                'extra_guest_minor' => $calculation->extraGuestMinor,
                'subtotal_minor' => $calculation->subtotalMinor,
                'discount_minor' => 0,
                'tax_rate_bps' => $calculation->taxRateBps,
                'tax_minor' => $calculation->taxMinor,
                'total_minor' => $calculation->totalMinor,
                'is_tax_inclusive' => $calculation->taxInclusive,
            ])->save();

            $booking->guests()->attach($guest->getKey(), [
                'booking_stay_id' => $stay->getKey(),
                'is_primary' => true,
                'primary_booking_guard' => $booking->getKey(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->allocation->allocate($stay, $actor);
            $booking = $this->numbers->assign($booking);

            $activity = $channel === BookingChannel::Web
                ? 'property-booking.web-booking.placed'
                : ($status === BookingStatus::Held ? 'property-booking.pob-hold.created' : 'property-booking.pob-booking.placed');
            $this->activities->record(
                activityType: $activity,
                description: ($channel === BookingChannel::Web ? 'Web booking ' : 'POB booking ')."{$booking->booking_number} placed",
                actor: $actor,
                subject: $booking,
                properties: [
                    'booking_ulid' => $booking->ulid,
                    'property_ulid' => $property->ulid,
                    'unit_type_ulid' => $unitType->ulid,
                    'payment_preference' => $paymentPreference?->value,
                    'shift_ulid' => $shift?->ulid,
                ],
                severity: SystemActivitySeverity::Notice,
                source: $channel === BookingChannel::Web ? 'property-booking-storefront' : 'property-booking-pob',
            );
            if ($status === BookingStatus::Confirmed) {
                BookingConfirmed::dispatch($booking->ulid, $booking->property_id);
            }

            return $booking->refresh()->load($channel === BookingChannel::Web
                ? ['property', 'primaryGuest', 'stays.unitType', 'unitAssignments']
                : $this->pobRelations());
        });
    }

    /** Require protected identity for POB guests when the adopter enables it. */
    private function assertPointOfBookingGuestIdentity(Guest $guest): void
    {
        if ((bool) config('property-booking.pob.guest_identity_required', true)
            && blank($guest->identity_number_hash)) {
            throw new PointOfBookingException('Record the guest ID or passport before continuing with this booking.');
        }
    }

    /** Resolve whether onsite bookings must observe public lead-time restrictions. */
    private function pointOfBookingAdvanceNoticePolicy(): AdvanceNoticePolicy
    {
        return (bool) config('property-booking.pob.enforce_advance_notice', false)
            ? AdvanceNoticePolicy::Enforce
            : AdvanceNoticePolicy::WaiveForOnSiteBooking;
    }

    /** Lock and validate a POB shift and its explicit operator contract. */
    private function assertPobShift(ReceptionShift $shift, User $actor, int $propertyId): ReceptionShift
    {
        $shift = ReceptionShift::query()->with(['register', 'property'])->lockForUpdate()->findOrFail($shift->getKey());
        $supervisor = $actor->can(PropertyBookingPermission::MANAGE_SHIFTS);
        if (! $shift->isOpen() || $shift->property_id !== $propertyId
            || (! $supervisor && $shift->receptionist_id !== $actor->getKey())) {
            throw new PointOfBookingException('An owned open reception shift is required for this booking.');
        }

        return $shift;
    }

    /** @return list<string> */
    private function pobRelations(): array
    {
        return [
            'property', 'primaryGuest', 'stays.unitType', 'stays.ratePlan',
            'unitAssignments.unit', 'payments', 'register', 'receptionShift', 'receptionist.profile',
        ];
    }

    /** Build a compact guest-visible property address snapshot. */
    private function addressSummary(Property $property): ?string
    {
        $value = collect([
            $property->address_line_1,
            $property->address_line_2,
            $property->city,
            $property->region,
            $property->country_code,
        ])->filter()->implode(', ');

        return $value !== '' ? $value : null;
    }

    /** Normalize optional public or internal text without carrying blanks. */
    private function nullableText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
