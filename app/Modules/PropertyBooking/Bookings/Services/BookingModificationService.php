<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Availability\Services\UnitAllocationService;
use App\Modules\PropertyBooking\Bookings\Data\BookingModificationData;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Events\BookingModified;
use App\Modules\PropertyBooking\Bookings\Exceptions\BookingOperationException;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingStay;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Models\AccommodationUnit;
use App\Modules\PropertyBooking\Catalog\Services\AccommodationUnitService;
use App\Modules\PropertyBooking\Pricing\Models\RatePlan;
use App\Modules\PropertyBooking\Pricing\Services\BookingRateCalculator;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use Illuminate\Database\DatabaseManager;

/** Owns authoritative interval repricing and concrete-unit moves. */
final readonly class BookingModificationService
{
    /** Create the modification service with locking and allocation collaborators. */
    public function __construct(
        private DatabaseManager $database,
        private BookingRateCalculator $rates,
        private UnitAllocationService $allocations,
        private AccommodationUnitService $units,
        private PropertyAccessService $propertyAccess,
        private RecordsSystemActivity $activities,
    ) {}

    /** Reprice and reallocate one version-one booking interval atomically. */
    public function modifyInterval(Booking $booking, BookingModificationData $data, User $actor): Booking
    {
        $reason = $this->requiredReason($data->reason);

        return $this->database->transaction(function () use ($booking, $data, $actor, $reason): Booking {
            $booking = Booking::query()->with('property')->lockForUpdate()->findOrFail($booking->getKey());
            $this->propertyAccess->authorize($actor, $booking->property_id);
            if (! in_array($booking->status, [BookingStatus::Pending, BookingStatus::Confirmed], true)
                || ! in_array($booking->stay_status, [StayStatus::Expected, StayStatus::CheckedIn], true)) {
                throw new BookingOperationException('Only an expected or checked-in active booking can be modified.');
            }
            if ($data->endsAt->lessThanOrEqualTo($data->startsAt) || $data->endsAt->isPast()) {
                throw new BookingOperationException('The modified stay must end after it starts and remain in the future.');
            }
            if ($booking->stay_status === StayStatus::CheckedIn
                && (! $data->startsAt->equalTo($booking->starts_at) || ! $data->endsAt->isAfter($booking->ends_at))) {
                throw new BookingOperationException('An in-house booking can only extend its existing departure.');
            }

            $stay = BookingStay::query()
                ->where('booking_id', $booking->getKey())
                ->lockForUpdate()
                ->sole();
            $ratePlan = RatePlan::query()
                ->with(['property', 'unitType'])
                ->lockForUpdate()
                ->findOrFail($stay->rate_plan_id);
            $assignment = UnitAssignment::query()
                ->active()
                ->where('booking_stay_id', $stay->getKey())
                ->with('unit')
                ->lockForUpdate()
                ->sole();
            $calculation = $this->rates->calculate(
                $ratePlan,
                $data->startsAt,
                $data->endsAt,
                $stay->adult_count,
                $stay->child_count,
                $stay->infant_count,
            );
            $total = max(
                0,
                $calculation->totalMinor + $booking->charges_subtotal_minor - $booking->discount_minor,
            );
            if ($total < $booking->paid_minor) {
                throw new BookingOperationException('The modified total cannot be lower than payments already collected.');
            }

            $original = [
                'starts_at' => $booking->starts_at->toIso8601String(),
                'ends_at' => $booking->ends_at->toIso8601String(),
                'total_minor' => (int) $booking->total_minor,
                'unit_ulid' => $assignment->unit->ulid,
            ];
            $unitId = (int) $assignment->unit_id;
            $this->allocations->release($assignment, 'Booking interval modified: '.$reason, $actor);

            $stay->forceFill([
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'pricing_unit' => $calculation->pricingUnit,
                'billable_units' => $calculation->billableUnits,
                'rate_plan_name' => $ratePlan->name,
                'rate_plan_code' => $ratePlan->code,
                'unit_rate_minor' => $calculation->unitRateMinor,
                'extra_guest_minor' => $calculation->extraGuestMinor,
                'subtotal_minor' => $calculation->subtotalMinor,
                'tax_rate_bps' => $calculation->taxRateBps,
                'tax_minor' => $calculation->taxMinor,
                'total_minor' => $calculation->totalMinor,
                'is_tax_inclusive' => $calculation->taxInclusive,
            ])->save();
            $booking->forceFill([
                'starts_at' => $data->startsAt,
                'ends_at' => $data->endsAt,
                'accommodation_subtotal_minor' => $calculation->subtotalMinor,
                'tax_minor' => $calculation->taxMinor,
                'total_minor' => $total,
                'required_deposit_minor' => $calculation->requiredDepositMinor,
                'tax_inclusive' => $calculation->taxInclusive,
                'payment_status' => $this->paymentStatus((int) $booking->paid_minor, $total),
                'updated_by' => $actor->getKey(),
            ])->save();
            $newAssignment = $this->allocations->allocate($stay->refresh(), $actor, $unitId);

            $this->activities->record(
                activityType: 'property-booking.booking.modified',
                description: "Booking {$booking->booking_number} interval modified",
                actor: $actor,
                subject: $booking,
                properties: [
                    'booking_ulid' => $booking->ulid,
                    'property_id' => $booking->property_id,
                    'from' => $original,
                    'to' => [
                        'starts_at' => $data->startsAt->toIso8601String(),
                        'ends_at' => $data->endsAt->toIso8601String(),
                        'total_minor' => $total,
                        'unit_ulid' => $newAssignment->unit->ulid,
                    ],
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-operations',
            );
            BookingModified::dispatch($booking->ulid, $booking->property_id, 'interval');

            return $this->freshAggregate($booking);
        });
    }

    /** Move one active stay to a ready compatible concrete unit atomically. */
    public function moveUnit(
        Booking $booking,
        AccommodationUnit $target,
        string $reason,
        User $actor,
    ): Booking {
        $reason = $this->requiredReason($reason);

        return $this->database->transaction(function () use ($booking, $target, $reason, $actor): Booking {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $this->propertyAccess->authorize($actor, $booking->property_id);
            if ($booking->status !== BookingStatus::Confirmed
                || ! in_array($booking->stay_status, [StayStatus::Expected, StayStatus::CheckedIn], true)) {
                throw new BookingOperationException('Only a confirmed expected or checked-in booking can move units.');
            }
            $stay = BookingStay::query()->where('booking_id', $booking->getKey())->lockForUpdate()->sole();
            $assignment = UnitAssignment::query()
                ->active()
                ->where('booking_stay_id', $stay->getKey())
                ->with('unit')
                ->lockForUpdate()
                ->sole();
            $target = AccommodationUnit::query()->lockForUpdate()->findOrFail($target->getKey());
            if ($target->getKey() === $assignment->unit_id) {
                throw new BookingOperationException('The selected unit is already assigned to this booking.');
            }
            if ($target->property_id !== $booking->property_id || $target->unit_type_id !== $stay->unit_type_id
                || ! $target->is_active || $target->operational_status !== UnitOperationalStatus::Ready) {
                throw new BookingOperationException('The target must be an active, ready, compatible unit in the same property.');
            }

            $previousUnit = $assignment->unit;
            $this->allocations->release($assignment, 'Booking unit moved: '.$reason, $actor);
            $newAssignment = $this->allocations->allocate($stay, $actor, (int) $target->getKey());
            if ($booking->stay_status === StayStatus::CheckedIn) {
                $this->units->transitionReadiness($previousUnit, UnitOperationalStatus::Dirty, $actor);
            }

            $booking->forceFill(['updated_by' => $actor->getKey()])->save();
            $this->activities->record(
                activityType: 'property-booking.booking.unit-moved',
                description: "Booking {$booking->booking_number} moved to unit {$newAssignment->unit->code}",
                actor: $actor,
                subject: $booking,
                properties: [
                    'booking_ulid' => $booking->ulid,
                    'property_id' => $booking->property_id,
                    'from_unit_ulid' => $previousUnit->ulid,
                    'to_unit_ulid' => $newAssignment->unit->ulid,
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-operations',
            );
            BookingModified::dispatch($booking->ulid, $booking->property_id, 'unit_move');

            return $this->freshAggregate($booking);
        });
    }

    /** Project aggregate settlement from paid and total minor units. */
    private function paymentStatus(int $paidMinor, int $totalMinor): BookingPaymentStatus
    {
        return match (true) {
            $paidMinor <= 0 => BookingPaymentStatus::Unpaid,
            $paidMinor >= $totalMinor => BookingPaymentStatus::Paid,
            default => BookingPaymentStatus::Partial,
        };
    }

    /** Normalize a required operational reason. */
    private function requiredReason(string $reason): string
    {
        $reason = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim($reason));
        $reason = is_string($reason) ? trim($reason) : '';
        if ($reason === '' || strlen($reason) > 255) {
            throw new BookingOperationException('Booking changes require a reason of at most 255 characters.');
        }

        return $reason;
    }

    /** Return the relationships required by the administration detail panel. */
    private function freshAggregate(Booking $booking): Booking
    {
        return $booking->refresh()->load([
            'property', 'primaryGuest', 'stays.ratePlan', 'stays.activeAssignment.unit',
            'unitAssignments.unit', 'guests', 'payments', 'charges', 'register',
            'receptionShift', 'receptionist.profile',
        ]);
    }
}
