<?php

/**
 * Owns register shifts: opening, manual cash movements, closing, and reconciliation.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\PointOfBooking\Services;

use App\Models\User;
use App\Modules\TravelTours\PointOfBooking\Data\ShiftMovementData;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftMovementType;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\TravelTours\PointOfBooking\Models\BookingRegister;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShiftMovement;
use Illuminate\Database\DatabaseManager;

/**
 * Expected cash is the signed sum of a shift's movements: the opening float,
 * cash payments and cash-in as positives, cash refunds and cash-out as
 * negatives. Every financial source writes exactly one movement, so the
 * expected figure is derived, never maintained by hand. One open shift per
 * operator and per register is enforced by the unique guard columns as well
 * as by the checks here (lock order: user → register).
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

    /** Open a shift on a register with a counted opening float. */
    public function open(User $operator, BookingRegister $register, int $openingFloatMinor, ?int $actorId = null): BookingShift
    {
        if ($openingFloatMinor < 0) {
            throw new PointOfBookingException('The opening float cannot be negative.');
        }

        return $this->database->transaction(function () use ($operator, $register, $openingFloatMinor, $actorId): BookingShift {
            User::query()->lockForUpdate()->findOrFail($operator->getKey());
            $register = BookingRegister::query()->lockForUpdate()->findOrFail($register->getKey());
            if (! $register->is_active) {
                throw new PointOfBookingException('This register is not active.');
            }
            if (BookingShift::query()->where('operator_id', $operator->getKey())->open()->exists()) {
                throw new PointOfBookingException('You already have an open shift; close it before opening another.');
            }
            if (BookingShift::query()->where('register_id', $register->getKey())->open()->exists()) {
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

            $this->writeMovement($shift, ShiftMovementType::OpeningFloat, 'opening-float:'.$shift->ulid, $openingFloatMinor, 'Opening float counted.', $actorId ?? $operator->getKey());

            return $shift->fresh('register');
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

    /** Close a shift against the counted cash and record the variance. */
    public function close(BookingShift $shift, int $actualCashMinor, ?string $notes, int $actorId): BookingShift
    {
        if ($actualCashMinor < 0) {
            throw new PointOfBookingException('Counted cash cannot be negative.');
        }

        return $this->database->transaction(function () use ($shift, $actualCashMinor, $notes, $actorId): BookingShift {
            $shift = $this->openShift($shift);
            if ($shift->operator_id !== $actorId && ! User::query()->findOrFail($actorId)->can('update', $shift)) {
                throw new PointOfBookingException('Only the operator or a shift manager can close this shift.');
            }
            $expected = $this->expectedCashMinor($shift);
            $shift->forceFill([
                'status' => ShiftStatus::Closed,
                'register_open_guard' => null,
                'operator_open_guard' => null,
                'expected_cash_minor' => $expected,
                'actual_cash_minor' => $actualCashMinor,
                'variance_minor' => $actualCashMinor - $expected,
                'closed_at' => now(),
                'closing_notes' => filled($notes) ? trim((string) $notes) : null,
            ])->save();

            return $shift->fresh('register');
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
                throw new PointOfBookingException('Only closed shifts can be reconciled.');
            }
            $shift->forceFill([
                'status' => ShiftStatus::Reconciled,
                'reconciled_at' => now(),
                'reconciled_by' => $actorId,
                'reconciliation_notes' => filled($notes) ? trim((string) $notes) : null,
            ])->save();

            return $shift->fresh('register');
        });
    }

    /** Whether a closing variance is large enough to need a manager's review. */
    public function exceedsVarianceThreshold(BookingShift $shift): bool
    {
        return abs((int) $shift->variance_minor) > (int) config('travel-tours.pob.variance_threshold_minor', 10000);
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
