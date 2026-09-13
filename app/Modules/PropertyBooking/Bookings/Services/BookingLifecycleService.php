<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\Bookings\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Availability\Services\UnitAllocationService;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Enums\StayStatus;
use App\Modules\PropertyBooking\Bookings\Events\BookingCancelled;
use App\Modules\PropertyBooking\Bookings\Events\BookingCheckedIn;
use App\Modules\PropertyBooking\Bookings\Events\BookingCheckedOut;
use App\Modules\PropertyBooking\Bookings\Events\BookingConfirmed;
use App\Modules\PropertyBooking\Bookings\Events\BookingMarkedNoShow;
use App\Modules\PropertyBooking\Bookings\Exceptions\BookingOperationException;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\UnitAssignment;
use App\Modules\PropertyBooking\Catalog\Enums\UnitOperationalStatus;
use App\Modules\PropertyBooking\Catalog\Services\AccommodationUnitService;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Owns locked booking confirmation, cancellation, presence, and completion transitions. */
final readonly class BookingLifecycleService
{
    /** Create the lifecycle service with its transactional domain collaborators. */
    public function __construct(
        private DatabaseManager $database,
        private UnitAllocationService $allocations,
        private AccommodationUnitService $units,
        private PropertyAccessService $propertyAccess,
        private RecordsSystemActivity $activities,
    ) {}

    /** Confirm one pending expected booking after payment and allocation checks. */
    public function confirm(Booking $booking, User $actor): Booking
    {
        return $this->database->transaction(function () use ($booking, $actor): Booking {
            $booking = $this->lockBooking($booking);
            $this->propertyAccess->authorize($actor, $booking->property_id);
            if ($booking->status !== BookingStatus::Pending || $booking->stay_status !== StayStatus::Expected) {
                throw new BookingOperationException('Only a pending expected booking can be confirmed.');
            }
            if ($booking->pending_expires_at?->isPast() === true) {
                throw new BookingOperationException('This pending booking has expired and cannot be confirmed.');
            }
            $this->assertConfirmationPayment($booking);
            $this->activeAssignments($booking, requireEveryStay: true);

            $booking->forceFill([
                'status' => BookingStatus::Confirmed,
                'confirmed_at' => now(),
                'pending_expires_at' => null,
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->record(
                $booking,
                $actor,
                'property-booking.booking.confirmed',
                "Booking {$booking->booking_number} confirmed",
            );
            BookingConfirmed::dispatch($booking->ulid, $booking->property_id);

            return $this->freshAggregate($booking);
        });
    }

    /** Cancel one pre-arrival booking and release every active allocation. */
    public function cancel(Booking $booking, string $reason, User $actor): Booking
    {
        $reason = $this->requiredReason($reason, 'Cancellation');

        return $this->database->transaction(function () use ($booking, $reason, $actor): Booking {
            $booking = $this->lockBooking($booking);
            $this->propertyAccess->authorize($actor, $booking->property_id);
            if (! in_array($booking->status, [BookingStatus::Held, BookingStatus::Pending, BookingStatus::Confirmed], true)
                || $booking->stay_status !== StayStatus::Expected) {
                throw new BookingOperationException('Only a held, pending, or confirmed pre-arrival booking can be cancelled.');
            }

            $this->releaseAssignments($booking, 'Booking cancelled: '.$reason, $actor);
            $booking->forceFill([
                'status' => BookingStatus::Cancelled,
                'cancellation_reason' => $reason,
                'cancelled_at' => now(),
                'hold_expires_at' => null,
                'pending_expires_at' => null,
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->record(
                $booking,
                $actor,
                'property-booking.booking.cancelled',
                "Booking {$booking->booking_number} cancelled",
                SystemActivitySeverity::Warning,
            );
            BookingCancelled::dispatch($booking->ulid, $booking->property_id);

            return $this->freshAggregate($booking);
        });
    }

    /** Mark one overdue confirmed arrival as a reviewed no-show. */
    public function markNoShow(Booking $booking, string $reason, User $actor): Booking
    {
        $reason = $this->requiredReason($reason, 'No-show');

        return $this->database->transaction(function () use ($booking, $reason, $actor): Booking {
            $booking = $this->lockBooking($booking);
            $this->propertyAccess->authorize($actor, $booking->property_id);
            if ($booking->status !== BookingStatus::Confirmed || $booking->stay_status !== StayStatus::Expected) {
                throw new BookingOperationException('Only a confirmed expected booking can be marked as a no-show.');
            }
            $eligibleAt = CarbonImmutable::instance($booking->starts_at)
                ->addMinutes(max(0, (int) config('property-booking.operations.no_show_after_minutes', 120)));
            if (CarbonImmutable::now()->isBefore($eligibleAt)) {
                throw new BookingOperationException('The configured no-show review threshold has not elapsed.');
            }

            $this->releaseAssignments($booking, 'Booking marked no-show: '.$reason, $actor);
            $booking->forceFill([
                'status' => BookingStatus::NoShow,
                'stay_status' => StayStatus::NoShow,
                'no_show_reason' => $reason,
                'no_show_at' => now(),
                'updated_by' => $actor->getKey(),
            ])->save();

            $this->record(
                $booking,
                $actor,
                'property-booking.booking.no-show',
                "Booking {$booking->booking_number} marked no-show",
                SystemActivitySeverity::Warning,
            );
            BookingMarkedNoShow::dispatch($booking->ulid, $booking->property_id);

            return $this->freshAggregate($booking);
        });
    }

    /** Check every occupant into one confirmed booking. */
    public function checkIn(Booking $booking, User $actor, ?string $overrideReason = null): Booking
    {
        return $this->database->transaction(function () use ($booking, $actor, $overrideReason): Booking {
            $booking = $this->lockBooking($booking);
            $this->propertyAccess->authorize($actor, $booking->property_id);
            if ($booking->status !== BookingStatus::Confirmed || $booking->stay_status !== StayStatus::Expected) {
                throw new BookingOperationException('Only a confirmed expected booking can be checked in.');
            }

            $assignments = $this->activeAssignments($booking, requireEveryStay: true);
            foreach ($assignments as $assignment) {
                if (! $assignment->unit->is_active
                    || $assignment->unit->operational_status !== UnitOperationalStatus::Ready) {
                    throw new BookingOperationException("Unit {$assignment->unit->code} must be active and ready before check-in.");
                }
            }
            $violations = $this->checkInViolations($booking);
            $override = $this->assertOverride($violations, $overrideReason, 'check-in');
            $now = now();

            $booking->forceFill([
                'stay_status' => StayStatus::CheckedIn,
                'checked_in_at' => $now,
                'updated_by' => $actor->getKey(),
            ])->save();
            DB::table('property_booking_guest_assignments')
                ->where('booking_id', $booking->getKey())
                ->lockForUpdate()
                ->update(['checked_in_at' => $now, 'checked_out_at' => null, 'updated_at' => $now]);

            $this->record(
                $booking,
                $actor,
                'property-booking.booking.checked-in',
                "Booking {$booking->booking_number} checked in",
                properties: ['override_used' => $override !== null, 'override_conditions' => $violations],
            );
            BookingCheckedIn::dispatch($booking->ulid, $booking->property_id);

            return $this->freshAggregate($booking);
        });
    }

    /** Check an in-house booking out, release units, and mark each vacated unit dirty. */
    public function checkOut(Booking $booking, User $actor, ?string $overrideReason = null): Booking
    {
        return $this->database->transaction(function () use ($booking, $actor, $overrideReason): Booking {
            $booking = $this->lockBooking($booking);
            $this->propertyAccess->authorize($actor, $booking->property_id);
            if ($booking->status !== BookingStatus::Confirmed || $booking->stay_status !== StayStatus::CheckedIn) {
                throw new BookingOperationException('Only a checked-in confirmed booking can be checked out.');
            }

            $assignments = $this->activeAssignments($booking, requireEveryStay: true);
            foreach ($assignments as $assignment) {
                if ($assignment->unit->operational_status !== UnitOperationalStatus::Ready) {
                    throw new BookingOperationException("Unit {$assignment->unit->code} must retain its in-house ready state before checkout.");
                }
            }
            $violations = $this->checkOutViolations($booking);
            $override = $this->assertOverride($violations, $overrideReason, 'check-out');
            $now = now();

            foreach ($assignments as $assignment) {
                $unit = $assignment->unit;
                $this->allocations->release($assignment, 'Guest checked out', $actor);
                $this->units->transitionReadiness($unit, UnitOperationalStatus::Dirty, $actor);
            }
            $booking->forceFill([
                'status' => BookingStatus::Completed,
                'stay_status' => StayStatus::CheckedOut,
                'checked_out_at' => $now,
                'completed_at' => $now,
                'updated_by' => $actor->getKey(),
            ])->save();
            DB::table('property_booking_guest_assignments')
                ->where('booking_id', $booking->getKey())
                ->lockForUpdate()
                ->update(['checked_out_at' => $now, 'updated_at' => $now]);

            $this->record(
                $booking,
                $actor,
                'property-booking.booking.checked-out',
                "Booking {$booking->booking_number} checked out",
                properties: ['override_used' => $override !== null, 'override_conditions' => $violations],
            );
            BookingCheckedOut::dispatch($booking->ulid, $booking->property_id);

            return $this->freshAggregate($booking);
        });
    }

    /** Lock the current booking and required property context. */
    private function lockBooking(Booking $booking): Booking
    {
        return Booking::query()
            ->with('property')
            ->lockForUpdate()
            ->findOrFail($booking->getKey());
    }

    /**
     * Return locked active assignments with concrete units.
     *
     * @return Collection<int, UnitAssignment>
     */
    private function activeAssignments(Booking $booking, bool $requireEveryStay): Collection
    {
        $assignments = UnitAssignment::query()
            ->active()
            ->where('booking_id', $booking->getKey())
            ->with('unit')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($requireEveryStay && $assignments->count() !== $booking->stays()->lockForUpdate()->count()) {
            throw new BookingOperationException('Every booking stay requires one active concrete-unit assignment.');
        }

        return $assignments;
    }

    /** Release every active allocation while preserving assignment history. */
    private function releaseAssignments(Booking $booking, string $reason, User $actor): void
    {
        foreach ($this->activeAssignments($booking, requireEveryStay: false) as $assignment) {
            $this->allocations->release($assignment, $reason, $actor);
        }
    }

    /** Enforce the configured payment threshold before confirmation. */
    private function assertConfirmationPayment(Booking $booking): void
    {
        $policy = (string) config('property-booking.operations.confirmation_payment_policy', 'deposit');
        $required = match ($policy) {
            'none' => 0,
            'full' => (int) $booking->total_minor,
            default => (int) $booking->required_deposit_minor,
        };
        if ($booking->paid_minor < $required) {
            throw new BookingOperationException('The configured confirmation payment requirement has not been met.');
        }
    }

    /** @return list<string> */
    private function checkInViolations(Booking $booking): array
    {
        $violations = [];
        $now = CarbonImmutable::now();
        $window = $this->localWindow(
            $booking,
            'check_in_from',
            'check_in_until',
            (int) config('property-booking.operations.check_in_early_minutes', 0),
            (int) config('property-booking.operations.check_in_late_minutes', 180),
            CarbonImmutable::instance($booking->starts_at),
        );
        if ($now->isBefore($window['from']) || $now->isAfter($window['until'])) {
            $violations[] = 'outside_check_in_window';
        }

        $policy = (string) config('property-booking.operations.check_in_payment_policy', 'deposit');
        $required = match ($policy) {
            'none' => 0,
            'full' => (int) $booking->total_minor,
            default => (int) $booking->required_deposit_minor,
        };
        if ($booking->paid_minor < $required) {
            $violations[] = 'payment_requirement_not_met';
        }

        return $violations;
    }

    /** @return list<string> */
    private function checkOutViolations(Booking $booking): array
    {
        $violations = [];
        $now = CarbonImmutable::now();
        $window = $this->localWindow(
            $booking,
            'check_out_from',
            'check_out_until',
            (int) config('property-booking.operations.check_out_early_minutes', 0),
            (int) config('property-booking.operations.check_out_late_minutes', 180),
            CarbonImmutable::instance($booking->ends_at),
        );
        if ($now->isBefore($window['from']) || $now->isAfter($window['until'])) {
            $violations[] = 'outside_check_out_window';
        }
        if ((string) config('property-booking.operations.check_out_payment_policy', 'full') === 'full'
            && $booking->paid_minor < $booking->total_minor) {
            $violations[] = 'outstanding_balance';
        }

        return $violations;
    }

    /**
     * Build one UTC operational window from property-local policy values.
     *
     * @return array{from: CarbonImmutable, until: CarbonImmutable}
     */
    private function localWindow(
        Booking $booking,
        string $fromField,
        string $untilField,
        int $earlyMinutes,
        int $lateMinutes,
        CarbonImmutable $scheduled,
    ): array {
        $timezone = $booking->property_timezone;
        $localScheduled = $scheduled->setTimezone($timezone);
        $fromValue = trim((string) $booking->property->getAttribute($fromField));
        $untilValue = trim((string) $booking->property->getAttribute($untilField));
        $from = $fromValue !== ''
            ? CarbonImmutable::parse($localScheduled->toDateString().' '.$fromValue, $timezone)
            : $localScheduled;
        $until = $untilValue !== ''
            ? CarbonImmutable::parse($localScheduled->toDateString().' '.$untilValue, $timezone)
            : $localScheduled;

        return [
            'from' => $from->subMinutes(max(0, $earlyMinutes))->utc(),
            'until' => $until->addMinutes(max(0, $lateMinutes))->utc(),
        ];
    }

    /** Require a bounded override only when operational preconditions fail. */
    private function assertOverride(array $violations, ?string $reason, string $operation): ?string
    {
        $reason = trim((string) $reason);
        if (strlen($reason) > 255) {
            throw new BookingOperationException('Override reasons cannot exceed 255 characters.');
        }
        if ($violations !== [] && $reason === '') {
            throw new BookingOperationException(ucfirst($operation).' requires an override reason: '.implode(', ', $violations).'.');
        }

        return $reason !== '' ? $reason : null;
    }

    /** Normalize a required lifecycle reason without accepting control characters. */
    private function requiredReason(string $reason, string $label): string
    {
        $reason = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim($reason));
        $reason = is_string($reason) ? trim($reason) : '';
        if ($reason === '' || strlen($reason) > 255) {
            throw new BookingOperationException("{$label} requires a reason of at most 255 characters.");
        }

        return $reason;
    }

    /** Record one privacy-safe lifecycle activity. */
    private function record(
        Booking $booking,
        User $actor,
        string $type,
        string $description,
        SystemActivitySeverity $severity = SystemActivitySeverity::Notice,
        array $properties = [],
    ): void {
        $this->activities->record(
            activityType: $type,
            description: $description,
            actor: $actor,
            subject: $booking,
            properties: ['booking_ulid' => $booking->ulid, 'property_id' => $booking->property_id, ...$properties],
            severity: $severity,
            source: 'property-booking-operations',
        );
    }

    /** Return the current aggregate relationships required by operations UI. */
    private function freshAggregate(Booking $booking): Booking
    {
        return $booking->refresh()->load([
            'property', 'primaryGuest', 'stays.ratePlan', 'stays.activeAssignment.unit',
            'guests', 'payments', 'charges', 'register', 'receptionShift', 'receptionist.profile',
        ]);
    }
}
