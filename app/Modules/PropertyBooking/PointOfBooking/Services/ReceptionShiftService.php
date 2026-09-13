<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentRecordStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingStatus;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\PointOfBooking\Enums\ReceptionShiftStatus;
use App\Modules\PropertyBooking\PointOfBooking\Events\ReceptionShiftVarianceDetected;
use App\Modules\PropertyBooking\PointOfBooking\Exceptions\ReceptionShiftException;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionRegister;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Support\PropertyAccessService;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Illuminate\Database\DatabaseManager;

/** Owns reception-shift opening, ownership, cash projection, and reconciliation. */
final readonly class ReceptionShiftService
{
    /** Create the shift service with transactional and property-scope collaborators. */
    public function __construct(
        private DatabaseManager $database,
        private PropertyAccessService $access,
        private RecordsSystemActivity $activities,
    ) {}

    /** Open one active property register for one eligible receptionist. */
    public function open(
        ReceptionRegister $register,
        User $receptionist,
        User $actor,
        int $openingFloatMinor,
        ?string $note = null,
    ): ReceptionShift {
        if ($openingFloatMinor < 0) {
            throw new ReceptionShiftException('Opening cash float cannot be negative.');
        }

        return $this->database->transaction(function () use ($register, $receptionist, $actor, $openingFloatMinor, $note): ReceptionShift {
            $register = ReceptionRegister::query()->with('property')->lockForUpdate()->findOrFail($register->getKey());
            $receptionist = User::query()->lockForUpdate()->findOrFail($receptionist->getKey());
            if (! $register->is_active) {
                throw new ReceptionShiftException('Inactive reception registers cannot open shifts.');
            }
            if (! $receptionist->is_active || ! $receptionist->can(PropertyBookingPermission::ACCESS_POB)) {
                throw new ReceptionShiftException('The selected receptionist is not an active Point of Booking operator.');
            }
            if (! $this->access->canAccess($actor, $register->property_id)
                || ! $this->access->canAccess($receptionist, $register->property_id)) {
                throw new ReceptionShiftException('The register and receptionist must belong to an authorized property scope.');
            }

            $conflict = ReceptionShift::query()
                ->where('status', ReceptionShiftStatus::Open->value)
                ->where(fn ($query) => $query
                    ->where('register_id', $register->getKey())
                    ->orWhere('receptionist_id', $receptionist->getKey()))
                ->lockForUpdate()
                ->exists();
            if ($conflict) {
                throw new ReceptionShiftException('The register or receptionist already has an open shift.');
            }

            $shift = new ReceptionShift;
            $shift->forceFill([
                'property_id' => $register->property_id,
                'register_id' => $register->getKey(),
                'receptionist_id' => $receptionist->getKey(),
                'register_open_guard' => $register->getKey(),
                'receptionist_open_guard' => $receptionist->getKey(),
                'status' => ReceptionShiftStatus::Open,
                'currency' => $register->property->currency,
                'opening_float_minor' => $openingFloatMinor,
                'expected_cash_minor' => $openingFloatMinor,
                'counted_cash_minor' => null,
                'variance_minor' => null,
                'opening_note' => $this->note($note),
                'closing_note' => null,
                'opened_by' => $actor->getKey(),
                'opened_at' => now(),
                'closed_by' => null,
                'closed_at' => null,
            ])->save();

            $this->activities->record(
                activityType: 'property-booking.shift.opened',
                description: "Reception shift opened on {$register->name}",
                actor: $actor,
                subject: $shift,
                properties: [
                    'shift_ulid' => $shift->ulid,
                    'register_ulid' => $register->ulid,
                    'receptionist_id' => $receptionist->getKey(),
                    'opening_float_minor' => $openingFloatMinor,
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-pob',
            );

            return $shift->refresh()->load(['property', 'register', 'receptionist.profile', 'opener.profile']);
        });
    }

    /** Recompute expected cash and persist an immutable closing snapshot once. */
    public function close(
        ReceptionShift $shift,
        User $actor,
        int $countedCashMinor,
        ?string $note = null,
    ): ReceptionShift {
        if ($countedCashMinor < 0) {
            throw new ReceptionShiftException('Counted cash cannot be negative.');
        }

        return $this->database->transaction(function () use ($shift, $actor, $countedCashMinor, $note): ReceptionShift {
            $shift = ReceptionShift::query()->lockForUpdate()->findOrFail($shift->getKey());
            if (! $shift->isOpen()) {
                throw new ReceptionShiftException('Only an open reception shift can be closed.');
            }
            if (! $this->access->canAccess($actor, $shift->property_id)) {
                throw new ReceptionShiftException('The reception shift is outside the operator property scope.');
            }
            if ($shift->bookings()->where('status', BookingStatus::Held->value)->lockForUpdate()->exists()) {
                throw new ReceptionShiftException('Resolve or discard held bookings before closing this shift.');
            }

            $expected = $shift->opening_float_minor + $this->netCash($shift);
            $variance = $countedCashMinor - $expected;
            $shift->forceFill([
                'register_open_guard' => null,
                'receptionist_open_guard' => null,
                'status' => ReceptionShiftStatus::Closed,
                'expected_cash_minor' => $expected,
                'counted_cash_minor' => $countedCashMinor,
                'variance_minor' => $variance,
                'closing_note' => $this->note($note),
                'closed_by' => $actor->getKey(),
                'closed_at' => now(),
            ])->save();

            $this->activities->record(
                activityType: 'property-booking.shift.closed',
                description: "Reception shift closed with variance {$variance}",
                actor: $actor,
                subject: $shift,
                properties: [
                    'shift_ulid' => $shift->ulid,
                    'expected_cash_minor' => $expected,
                    'counted_cash_minor' => $countedCashMinor,
                    'variance_minor' => $variance,
                ],
                severity: $variance === 0 ? SystemActivitySeverity::Notice : SystemActivitySeverity::Warning,
                source: 'property-booking-pob',
            );

            $threshold = max(1, (int) config('property-booking.pob.shift_variance_threshold_minor', 10_000));
            if ($variance !== 0 && abs($variance) >= $threshold) {
                ReceptionShiftVarianceDetected::dispatch($shift->ulid, $variance, $threshold, $shift->property_id);
            }

            return $shift->refresh()->load(['property', 'register', 'receptionist.profile', 'opener.profile', 'closer.profile']);
        });
    }

    /** Project completed cash less explicit cash refunds for one shift. */
    private function netCash(ReceptionShift $shift): int
    {
        $payments = BookingPayment::query()
            ->where('reception_shift_id', $shift->getKey())
            ->where('method', BookingPaymentMethod::Cash->value)
            ->whereIn('status', [
                BookingPaymentRecordStatus::Completed->value,
                BookingPaymentRecordStatus::Refunded->value,
            ])
            ->lockForUpdate()
            ->get(['amount_minor', 'refunded_amount_minor']);

        return (int) $payments->sum(
            static fn (BookingPayment $payment): int => $payment->amount_minor - $payment->refunded_amount_minor,
        );
    }

    /** Normalize bounded operational notes. */
    private function note(?string $note): ?string
    {
        $note = trim((string) $note);
        if (strlen($note) > 2000) {
            throw new ReceptionShiftException('Reception shift notes cannot exceed 2,000 characters.');
        }

        return $note !== '' ? $note : null;
    }
}
