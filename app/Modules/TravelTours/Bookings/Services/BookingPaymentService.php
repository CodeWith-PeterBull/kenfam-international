<?php

/**
 * Implements a focused TravelTours domain or application service.
 */

declare(strict_types=1);

namespace App\Modules\TravelTours\Bookings\Services;

use App\Modules\TravelTours\Bookings\Data\BookingPaymentData;
use App\Modules\TravelTours\Bookings\Enums\PaymentMethod;
use App\Modules\TravelTours\Bookings\Enums\PaymentRecordStatus;
use App\Modules\TravelTours\Bookings\Enums\PaymentStatus;
use App\Modules\TravelTours\Bookings\Enums\RefundStatus;
use App\Modules\TravelTours\Bookings\Exceptions\DuplicateOperationConflict;
use App\Modules\TravelTours\Bookings\Exceptions\PaymentException;
use App\Modules\TravelTours\Bookings\Models\BookingPayment;
use App\Modules\TravelTours\Bookings\Models\PaymentSchedule;
use App\Modules\TravelTours\Bookings\Models\TourBooking;
use App\Modules\TravelTours\Contracts\ProcessesBookingPayments;
use App\Modules\TravelTours\Events\BookingPaymentConfirmed;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftMovementType;
use App\Modules\TravelTours\PointOfBooking\Enums\ShiftStatus;
use App\Modules\TravelTours\PointOfBooking\Models\BookingShift;
use Illuminate\Support\Facades\DB;

/** Records manual payments idempotently and synchronizes aggregate settlement. */
final class BookingPaymentService implements ProcessesBookingPayments
{
    /** Inject instalment allocation rather than embedding schedule writes. */
    public function __construct(private readonly PaymentScheduleService $schedules) {}

    /** Record one confirmed payment while preserving retry and aggregate integrity. */
    public function record(TourBooking $booking, BookingPaymentData $data): BookingPayment
    {
        return DB::transaction(function () use ($booking, $data): BookingPayment {
            $locked = TourBooking::query()->lockForUpdate()->findOrFail($booking->id);
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

            $confirmed = (int) $locked->payments()->where('status', PaymentRecordStatus::Confirmed->value)->sum('amount_minor');
            $refunded = (int) $locked->refunds()->where('status', RefundStatus::Processed->value)->sum('amount_minor');
            $outstanding = max((int) $locked->total_minor - max($confirmed - $refunded, 0), 0);
            if ($data->amountMinor > $outstanding) {
                throw new PaymentException('Payment exceeds the outstanding booking balance.');
            }

            if ($data->paymentScheduleId !== null) {
                PaymentSchedule::query()
                    ->whereKey($data->paymentScheduleId)
                    ->where('booking_id', $locked->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $shiftId = $data->shiftId ?? $locked->shift_id;
            if ($shiftId !== null && $locked->shift_id !== null && $shiftId !== $locked->shift_id) {
                throw new PaymentException('Payment shift does not match the booking-desk shift.');
            }
            $shift = $shiftId !== null
                ? BookingShift::query()->lockForUpdate()->findOrFail($shiftId)
                : null;
            if ($shift !== null && $shift->status !== ShiftStatus::Open) {
                throw new PaymentException('The selected booking-desk shift is not open.');
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
                'status' => PaymentRecordStatus::Confirmed,
                'paid_at' => now(),
                'received_by' => $data->actorId,
                'safe_metadata' => $data->safeMetadata,
            ]);
            $payment->save();

            $this->schedules->allocate($locked, $data->amountMinor, $data->paymentScheduleId);

            $confirmed += $data->amountMinor;
            $netPaid = max($confirmed - $refunded, 0);
            $locked->forceFill([
                'paid_minor' => $confirmed,
                'refunded_minor' => $refunded,
                'payment_status' => $netPaid >= $locked->total_minor
                    ? PaymentStatus::Paid
                    : ($netPaid > 0 ? PaymentStatus::Partial : PaymentStatus::Unpaid),
            ])->save();

            if ($shiftId !== null && $data->method === PaymentMethod::Cash) {
                if ($data->actorId === null) {
                    throw new PaymentException('Cash booking-desk payments require an authenticated actor.');
                }

                $movement = $shift?->movements()->make();
                if ($movement === null) {
                    throw new PaymentException('The selected booking-desk shift is unavailable.');
                }
                $movement->forceFill([
                    'operation_key' => hash('sha256', 'payment-movement:'.$data->operationKey),
                    'booking_id' => $locked->id,
                    'payment_id' => $payment->id,
                    'movement_type' => ShiftMovementType::Payment,
                    'amount_minor' => $data->amountMinor,
                    'currency' => $currency,
                    'actor_id' => $data->actorId,
                    'occurred_at' => now(),
                ])->save();
            }

            BookingPaymentConfirmed::dispatch($payment);

            return $payment->fresh(['booking', 'paymentSchedule', 'shift']);
        }, 3);
    }
}
