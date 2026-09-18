<?php

/**
 * Implements a focused TravelTours domain or application service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Services;

use App\Modules\TravelTours\Bookings\Data\BookingPaymentData;
use App\Modules\TravelTours\Bookings\Data\BookingRefundData;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentStatus;
use App\Modules\TravelTours\Bookings\Enums\RefundStatus;
use App\Modules\TravelTours\Bookings\Exceptions\DuplicateOperationConflict;
use App\Modules\TravelTours\Bookings\Exceptions\PaymentException;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Bookings\Models\BookingRefund;
use App\Modules\TravelTours\Bookings\Models\PaymentSchedule;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Contracts\ProcessesBookingPayments;
use App\Modules\TravelTours\Events\BookingPaymentConfirmed;
use App\Modules\TravelTours\Events\BookingPaymentRecorded;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftMovementType;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records manual payments idempotently and synchronizes aggregate settlement.
 *
 * Money moves in two steps: a payment is recorded as pending evidence, then
 * confirmed by an authorized operator, at which point aggregates, instalments,
 * and the booking-desk cash movement are written. record() performs both steps
 * for money received in hand. A future gateway callback uses the same seam:
 * recordPending() with provider and transaction identifier, then confirm().
 */
final class BookingPaymentService implements ProcessesBookingPayments
{
    /** Inject instalment allocation rather than embedding schedule writes. */
    public function __construct(private readonly PaymentScheduleService $schedules) {}

    /** Record one confirmed payment while preserving retry and aggregate integrity. */
    public function record(TourBooking $booking, BookingPaymentData $data): BookingPayment
    {
        return DB::transaction(function () use ($booking, $data): BookingPayment {
            $payment = $this->recordPending($booking, $data);

            return $this->confirm($payment, $data->actorId);
        }, 3);
    }

    /** Record payment evidence awaiting confirmation; aggregates are untouched until then. */
    public function recordPending(TourBooking $booking, BookingPaymentData $data): BookingPayment
    {
        return DB::transaction(function () use ($booking, $data): BookingPayment {
            $locked = TourBooking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $currency = mb_strtoupper($data->currency);
            $existing = BookingPayment::query()->where('operation_key', $data->operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->booking_id !== $locked->id
                    || $existing->amount_minor !== $data->amountMinor
                    || $existing->currency !== $currency
                    || $existing->method !== $data->method) {
                    throw new DuplicateOperationConflict('The payment operation key was already used with different payment data.');
                }

                return $existing;
            }

            if ($locked->status->isTerminal()) {
                throw new PaymentException('Payments cannot be recorded against a terminal booking.');
            }
            if ($locked->currency !== $currency) {
                throw new PaymentException('Payment currency must match the booking currency.');
            }
            if ($data->amountMinor > $this->outstandingMinor($locked)) {
                throw new PaymentException('Payment exceeds the outstanding booking balance.');
            }
            if ($data->paymentScheduleId !== null) {
                PaymentSchedule::query()->whereKey($data->paymentScheduleId)->where('booking_id', $locked->id)->lockForUpdate()->firstOrFail();
            }
            $shiftId = $data->shiftId ?? $locked->shift_id;
            if ($shiftId !== null && $locked->shift_id !== null && $shiftId !== $locked->shift_id) {
                throw new PaymentException('Payment shift does not match the booking-desk shift.');
            }
            if ($shiftId !== null) {
                $this->openShift($shiftId);
            }

            $payment = new BookingPayment;
            $payment->forceFill([
                'booking_id' => $locked->id,
                'operation_key' => $data->operationKey,
                'payment_schedule_id' => $data->paymentScheduleId,
                'shift_id' => $shiftId,
                'method' => $data->method,
                'provider' => $data->provider,
                'reference' => $data->reference,
                'transaction_identifier' => $data->transactionIdentifier,
                'amount_minor' => $data->amountMinor,
                'currency' => $currency,
                'status' => PaymentRecordStatus::Pending,
                'paid_at' => null,
                'received_by' => $data->actorId,
                'safe_metadata' => $data->safeMetadata,
            ]);
            $payment->save();
            BookingPaymentRecorded::dispatch($payment);

            return $payment;
        }, 3);
    }

    /**
     * Confirm pending payment evidence: settle aggregates and instalments, then
     * record the booking-desk cash movement when the money went into a drawer.
     */
    public function confirm(BookingPayment $payment, ?int $actorId = null): BookingPayment
    {
        return DB::transaction(function () use ($payment, $actorId): BookingPayment {
            $locked = TourBooking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $payment = BookingPayment::query()->lockForUpdate()->findOrFail($payment->getKey());
            if ($payment->status === PaymentRecordStatus::Confirmed) {
                return $payment->fresh(['booking', 'paymentSchedule', 'shift']);
            }
            if ($payment->status !== PaymentRecordStatus::Pending) {
                throw new PaymentException('Only pending payments can be confirmed.');
            }
            if ($locked->status->isTerminal()) {
                throw new PaymentException('Payments cannot be confirmed against a terminal booking.');
            }
            if ($payment->amount_minor > $this->outstandingMinor($locked)) {
                throw new PaymentException('Payment exceeds the outstanding booking balance.');
            }

            $payment->forceFill([
                'status' => PaymentRecordStatus::Confirmed,
                'paid_at' => now(),
                'received_by' => $actorId ?? $payment->received_by,
            ])->save();
            $this->schedules->allocate($locked, $payment->amount_minor, $payment->payment_schedule_id);
            $this->syncAggregates($locked);

            if ($payment->shift_id !== null && $payment->method === PaymentMethod::Cash) {
                $this->recordCashMovement(
                    $this->openShift($payment->shift_id),
                    ShiftMovementType::Payment,
                    hash('sha256', 'payment-movement:'.$payment->operation_key),
                    $locked,
                    $payment->amount_minor,
                    $actorId ?? $payment->received_by,
                    paymentId: $payment->id,
                );
            }

            BookingPaymentConfirmed::dispatch($payment);

            return $payment->fresh(['booking', 'paymentSchedule', 'shift']);
        }, 3);
    }

    /** Mark pending payment evidence as failed, keeping the row for audit. */
    public function reject(BookingPayment $payment, ?int $actorId, string $reason): BookingPayment
    {
        return DB::transaction(function () use ($payment, $actorId, $reason): BookingPayment {
            TourBooking::query()->lockForUpdate()->findOrFail($payment->booking_id);
            $payment = BookingPayment::query()->lockForUpdate()->findOrFail($payment->getKey());
            if ($payment->status === PaymentRecordStatus::Failed) {
                return $payment;
            }
            if ($payment->status !== PaymentRecordStatus::Pending) {
                throw new PaymentException('Only pending payments can be rejected.');
            }
            if (trim($reason) === '') {
                throw new PaymentException('Rejecting a payment requires a reason.');
            }

            $payment->forceFill([
                'status' => PaymentRecordStatus::Failed,
                'received_by' => $actorId ?? $payment->received_by,
                'safe_metadata' => array_merge($payment->safe_metadata ?? [], ['rejection_reason' => trim($reason)]),
            ])->save();

            return $payment;
        }, 3);
    }

    /**
     * Record a processed manual refund against the booking's confirmed money.
     *
     * The original payment, when named, is locked and the refund cannot exceed
     * what remains of it; otherwise the ceiling is the booking's net paid amount.
     * Cash refunds handed back at a booking desk write a negative shift movement.
     */
    public function refund(TourBooking $booking, BookingRefundData $data): BookingRefund
    {
        return DB::transaction(function () use ($booking, $data): BookingRefund {
            $locked = TourBooking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $currency = mb_strtoupper($data->currency);
            $existing = BookingRefund::query()->where('operation_key', $data->operationKey)->lockForUpdate()->first();
            if ($existing !== null) {
                if ($existing->booking_id !== $locked->id || $existing->amount_minor !== $data->amountMinor || $existing->currency !== $currency) {
                    throw new DuplicateOperationConflict('The refund operation key was already used with different refund data.');
                }

                return $existing;
            }
            if ($locked->currency !== $currency) {
                throw new PaymentException('Refund currency must match the booking currency.');
            }

            $ceiling = $this->netPaidMinor($locked);
            $payment = null;
            if ($data->paymentId !== null) {
                $payment = BookingPayment::query()->where('booking_id', $locked->id)->lockForUpdate()->findOrFail($data->paymentId);
                if ($payment->status !== PaymentRecordStatus::Confirmed) {
                    throw new PaymentException('Only confirmed payments can be refunded.');
                }
                $alreadyRefunded = (int) BookingRefund::query()->where('payment_id', $payment->id)->where('status', RefundStatus::Processed->value)->sum('amount_minor');
                $ceiling = min($ceiling, max($payment->amount_minor - $alreadyRefunded, 0));
            }
            if ($data->amountMinor > $ceiling) {
                throw new PaymentException('Refund exceeds the amount available to refund.');
            }

            $refund = new BookingRefund;
            $refund->forceFill([
                'booking_id' => $locked->id,
                'payment_id' => $payment?->id,
                'operation_key' => $data->operationKey,
                'reference' => 'RF-'.Str::upper((string) Str::ulid()),
                'amount_minor' => $data->amountMinor,
                'currency' => $currency,
                'reason' => trim($data->reason),
                'status' => RefundStatus::Processed,
                'processor' => $data->processor,
                'transaction_identifier' => $data->transactionIdentifier,
                'requested_by' => $data->actorId,
                'requested_at' => now(),
                'processed_by' => $data->actorId,
                'completed_at' => now(),
                'safe_metadata' => $data->safeMetadata,
            ]);
            $refund->save();
            $this->syncAggregates($locked);

            $shiftId = $data->shiftId ?? $payment?->shift_id;
            if ($shiftId !== null && ($payment?->method ?? PaymentMethod::Cash) === PaymentMethod::Cash) {
                $this->recordCashMovement(
                    $this->openShift($shiftId),
                    ShiftMovementType::Refund,
                    hash('sha256', 'refund-movement:'.$data->operationKey),
                    $locked,
                    -$data->amountMinor,
                    $data->actorId,
                    refundId: $refund->id,
                    reason: trim($data->reason),
                );
            }

            return $refund->fresh(['booking', 'payment']);
        }, 3);
    }

    /** Recompute paid, refunded, and the payment status from persisted rows. */
    private function syncAggregates(TourBooking $locked): void
    {
        $confirmed = (int) $locked->payments()->where('status', PaymentRecordStatus::Confirmed->value)->sum('amount_minor');
        $refunded = (int) $locked->refunds()->where('status', RefundStatus::Processed->value)->sum('amount_minor');
        $netPaid = max($confirmed - $refunded, 0);

        $status = match (true) {
            $refunded > 0 && $netPaid === 0 => PaymentStatus::Refunded,
            $refunded > 0 => PaymentStatus::PartiallyRefunded,
            $netPaid >= (int) $locked->total_minor => PaymentStatus::Paid,
            $netPaid > 0 => PaymentStatus::Partial,
            default => PaymentStatus::Unpaid,
        };

        $locked->forceFill(['paid_minor' => $confirmed, 'refunded_minor' => $refunded, 'payment_status' => $status])->save();
    }

    /** Money still owed on a locked booking after confirmed payments net of refunds. */
    private function outstandingMinor(TourBooking $locked): int
    {
        return max((int) $locked->total_minor - $this->netPaidMinor($locked), 0);
    }

    /** Confirmed payments net of processed refunds for a locked booking. */
    private function netPaidMinor(TourBooking $locked): int
    {
        $confirmed = (int) $locked->payments()->where('status', PaymentRecordStatus::Confirmed->value)->sum('amount_minor');
        $refunded = (int) $locked->refunds()->where('status', RefundStatus::Processed->value)->sum('amount_minor');

        return max($confirmed - $refunded, 0);
    }

    /** Lock a shift and require it to be open before any cash is attributed to it. */
    private function openShift(int $shiftId): BookingShift
    {
        $shift = BookingShift::query()->lockForUpdate()->findOrFail($shiftId);
        if ($shift->status !== ShiftStatus::Open) {
            throw new PaymentException('The selected booking-desk shift is not open.');
        }

        return $shift;
    }

    /** Write exactly one cash movement for a financial source, attributed to an authenticated actor. */
    private function recordCashMovement(
        BookingShift $shift,
        ShiftMovementType $type,
        string $operationKey,
        TourBooking $booking,
        int $amountMinor,
        ?int $actorId,
        ?int $paymentId = null,
        ?int $refundId = null,
        ?string $reason = null,
    ): void {
        if ($actorId === null) {
            throw new PaymentException('Cash booking-desk movements require an authenticated actor.');
        }

        $shift->movements()->make()->forceFill([
            'operation_key' => $operationKey,
            'booking_id' => $booking->id,
            'payment_id' => $paymentId,
            'refund_id' => $refundId,
            'movement_type' => $type,
            'amount_minor' => $amountMinor,
            'currency' => $booking->currency,
            'reason' => $reason,
            'actor_id' => $actorId,
            'occurred_at' => now(),
        ])->save();
    }
}
