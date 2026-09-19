<?php

/**
 * Owns register shifts: opening, manual cash movements, closing, and reconciliation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Services;

use App\Models\User;
use App\Modules\TravelTours\Bookings\Enums\BookingStatus;
use App\Modules\TravelTours\PointOfBooking\Data\ShiftMovementData;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftMovementType;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Events\ShiftVarianceDetected;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShiftMovement;
use App\Modules\TravelTours\Support\TravelToursPermission;
use Illuminate\Database\DatabaseManager;

/**
 * A manager opens a shift on a register for one operator and closes it
 * against the counted cash. Expected cash is the signed sum of the shift's
 * movements: the opening float, cash payments and cash-in as positives, cash
 * refunds and cash-out as negatives. Every financial source writes exactly
 * one movement, so the expected figure is derived, never maintained by hand.
 * One open shift per operator and per register is enforced by the unique
 * guard columns as well as by the checks here (lock order: user → register).
 */
final readonly class BookingShiftService
{
    /** Create the service with its transaction boundary. */
    public function __construct(private DatabaseManager $database) {}

    /** The operator's open shift, if any. */
    public function currentFor(User $operator): ?BookingShift
    {
        return BookingShift::query()->with('register')->where('operator_id', $operator->getKey())->open()->latest('opened_at')->first();
    }

    /** Open one active register for one eligible operator with a counted opening float. */
    public function open(BookingRegister $register, User $operator, User $actor, int $openingFloatMinor, ?string $note = null): BookingShift
    {
        if ($openingFloatMinor < 0) {
            throw new PointOfBookingException('The opening float cannot be negative.');
        }
        $note = $this->note($note);

        return $this->database->transaction(function () use ($register, $operator, $actor, $openingFloatMinor, $note): BookingShift {
            $operator = User::query()->lockForUpdate()->findOrFail($operator->getKey());
            $register = BookingRegister::query()->lockForUpdate()->findOrFail($register->getKey());
            if (! $register->is_active) {
                throw new PointOfBookingException('Inactive registers cannot open shifts.');
            }
            if (! $operator->is_active || ! $operator->can(TravelToursPermission::ACCESS_POB)) {
                throw new PointOfBookingException('The selected operator is not an active booking-desk operator.');
            }
            if (BookingShift::query()->where('operator_id', $operator->getKey())->open()->lockForUpdate()->exists()) {
                throw new PointOfBookingException('The operator already has an open shift; close it before opening another.');
            }
            if (BookingShift::query()->where('register_id', $register->getKey())->open()->lockForUpdate()->exists()) {
                throw new PointOfBookingException('Another operator has an open shift on this register.');
            }

            $shift = new BookingShift;
            $shift->forceFill([
                'register_id' => $register->getKey(),
                'operator_id' => $operator->getKey(),
                'register_open_guard' => $register->getKey(),
                'operator_open_guard' => $operator->getKey(),
                'status' => ShiftStatus::Open,
                'currency' => strtoupper((string) config('travel-tours.defaults.currency', 'KES')),
                'currency_exponent' => (int) config('travel-tours.defaults.currency_decimals', 2),
                'opening_float_minor' => $openingFloatMinor,
                'expected_cash_minor' => $openingFloatMinor,
                'opened_at' => now(),
            ])->save();

            $this->writeMovement($shift, ShiftMovementType::OpeningFloat, 'opening-float:'.$shift->ulid, $openingFloatMinor, $note ?? 'Opening float counted.', $actor->getKey());

            return $shift->fresh(['register', 'operator']);
        });
    }

    /** Declare cash put into or taken out of the drawer outside a sale. */
    public function recordMovement(BookingShift $shift, ShiftMovementData $data, int $actorId): BookingShiftMovement
    {
        return $this->database->transaction(function () use ($shift, $data, $actorId): BookingShiftMovement {
            $shift = $this->openShift($shift);
            $existing = BookingShiftMovement::query()->where('operation_key', $data->operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                return $existing;
            }
            $signed = $data->type === ShiftMovementType::CashOut ? -$data->amountMinor : $data->amountMinor;

            return $this->writeMovement($shift, $data->type, $data->operationKey, $signed, trim($data->reason), $actorId);
        });
    }

    /** Expected cash in the drawer right now, derived from the movement ledger. */
    public function expectedCashMinor(BookingShift $shift): int
    {
        return (int) $shift->movements()->sum('amount_minor');
    }

    /**
     * Close a shift against the counted cash and record the variance.
     *
     * Holds still pending on the shift must be settled or discarded first, so
     * a closed drawer never has money still expected of it.
     */
    public function close(BookingShift $shift, User $actor, int $countedCashMinor, ?string $note = null): BookingShift
    {
        if ($countedCashMinor < 0) {
            throw new PointOfBookingException('Counted cash cannot be negative.');
        }
        $note = $this->note($note);

        return $this->database->transaction(function () use ($shift, $actor, $countedCashMinor, $note): BookingShift {
            $shift = $this->openShift($shift);
            if ($shift->operator_id !== $actor->getKey() && ! $actor->can(TravelToursPermission::MANAGE_SHIFTS)) {
                throw new PointOfBookingException('Only the operator or a shift manager can close this shift.');
            }
            if ($shift->bookings()->where('status', BookingStatus::Pending->value)->lockForUpdate()->exists()) {
                throw new PointOfBookingException('Settle or discard the held bookings on this shift before closing it.');
            }
            $expected = $this->expectedCashMinor($shift);
            $variance = $countedCashMinor - $expected;
            $shift->forceFill([
                'status' => ShiftStatus::Closed,
                'register_open_guard' => null,
                'operator_open_guard' => null,
                'expected_cash_minor' => $expected,
                'actual_cash_minor' => $countedCashMinor,
                'variance_minor' => $variance,
                'closed_at' => now(),
                'closing_notes' => $note,
            ])->save();

            $threshold = max(1, (int) config('travel-tours.pob.variance_threshold_minor', 10_000));
            if ($variance !== 0 && abs($variance) >= $threshold) {
                ShiftVarianceDetected::dispatch($shift->ulid, $variance, $threshold);
            }

            return $shift->fresh(['register', 'operator']);
        });
    }

    /** Sign off a closed shift after its variance has been reviewed. */
    public function reconcile(BookingShift $shift, ?string $notes, int $actorId): BookingShift
    {
        return $this->database->transaction(function () use ($shift, $notes, $actorId): BookingShift {
            $shift = BookingShift::query()->lockForUpdate()->findOrFail($shift->getKey());
            if ($shift->status === ShiftStatus::Reconciled) {
                return $shift;
            }
            if ($shift->status !== ShiftStatus::Closed) {
                throw new PointOfBookingException('Only closed shifts can be signed off.');
            }
            $shift->forceFill([
                'status' => ShiftStatus::Reconciled,
                'reconciled_at' => now(),
                'reconciled_by' => $actorId,
                'reconciliation_notes' => $this->note($notes),
            ])->save();

            return $shift->fresh(['register', 'operator']);
        });
    }

    /** Whether a closing variance is large enough to need a manager's review. */
    public function exceedsVarianceThreshold(BookingShift $shift): bool
    {
        return abs((int) $shift->variance_minor) >= max(1, (int) config('travel-tours.pob.variance_threshold_minor', 10_000));
    }

    /** Lock a shift and require it to be open. */
    private function openShift(BookingShift $shift): BookingShift
    {
        $shift = BookingShift::query()->lockForUpdate()->findOrFail($shift->getKey());
        if ($shift->status !== ShiftStatus::Open) {
            throw new PointOfBookingException('This shift is not open.');
        }

        return $shift;
    }

    /** Normalise a bounded operational note. */
    private function note(?string $note): ?string
    {
        $note = trim((string) $note);
        if (mb_strlen($note) > 2000) {
            throw new PointOfBookingException('Shift notes cannot exceed 2,000 characters.');
        }

        return $note !== '' ? $note : null;
    }

    /** Append one signed movement to the shift ledger. */
    private function writeMovement(BookingShift $shift, ShiftMovementType $type, string $operationKey, int $signedMinor, string $reason, int $actorId): BookingShiftMovement
    {
        $movement = $shift->movements()->make();
        $movement->forceFill([
            'operation_key' => $operationKey,
            'movement_type' => $type,
            'amount_minor' => $signedMinor,
            'currency' => $shift->currency,
            'reason' => $reason,
            'actor_id' => $actorId,
            'occurred_at' => now(),
        ])->save();

        return $movement;
    }
}
