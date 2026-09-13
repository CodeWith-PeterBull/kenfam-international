<?php

declare(strict_types=1);

namespace App\Modules\PropertyBooking\PointOfBooking\Services;

use App\Contracts\RecordsSystemActivity;
use App\Enums\SystemActivitySeverity;
use App\Models\User;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentMethod;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentRecordStatus;
use App\Modules\PropertyBooking\Bookings\Enums\BookingPaymentStatus;
use App\Modules\PropertyBooking\Bookings\Events\BookingPaymentConfirmed;
use App\Modules\PropertyBooking\Bookings\Models\Booking;
use App\Modules\PropertyBooking\Bookings\Models\BookingPayment;
use App\Modules\PropertyBooking\PointOfBooking\Data\PobTenderData;
use App\Modules\PropertyBooking\PointOfBooking\Exceptions\PointOfBookingException;
use App\Modules\PropertyBooking\PointOfBooking\Models\ReceptionShift;
use App\Modules\PropertyBooking\Support\PropertyBookingPermission;
use Illuminate\Database\DatabaseManager;

/** Persists privacy-safe POB tenders and refreshes aggregate settlement state. */
final readonly class BookingPaymentService
{
    /** Create the payment service with its transaction and audit collaborators. */
    public function __construct(
        private DatabaseManager $database,
        private RecordsSystemActivity $activities,
    ) {}

    /** Record one completed tender against a locked booking and open shift. */
    public function recordCompleted(
        Booking $booking,
        PobTenderData $tender,
        ReceptionShift $shift,
        User $actor,
    ): BookingPayment {
        return $this->database->transaction(function () use ($booking, $tender, $shift, $actor): BookingPayment {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->getKey());
            $shift = ReceptionShift::query()->lockForUpdate()->findOrFail($shift->getKey());
            $this->assertTender($booking, $tender, $shift, $actor);
            $reference = $this->reference($tender);
            if ($reference !== null && BookingPayment::query()
                ->where('method', $tender->method->value)
                ->where('reference', $reference)
                ->where('status', BookingPaymentRecordStatus::Completed->value)
                ->lockForUpdate()
                ->exists()) {
                throw new PointOfBookingException('That completed payment reference has already been recorded.');
            }

            $tendered = $tender->method === BookingPaymentMethod::Cash
                ? ($tender->tenderedMinor ?? $tender->amountMinor)
                : null;
            $payment = new BookingPayment;
            $payment->forceFill([
                'booking_id' => $booking->getKey(),
                'reception_shift_id' => $shift->getKey(),
                'recorded_by' => $actor->getKey(),
                'method' => $tender->method,
                'status' => BookingPaymentRecordStatus::Completed,
                'currency' => $booking->currency,
                'amount_minor' => $tender->amountMinor,
                'tendered_minor' => $tendered,
                'change_minor' => $tendered === null ? 0 : $tendered - $tender->amountMinor,
                'refunded_amount_minor' => 0,
                'reference' => $reference,
                'metadata' => null,
                'paid_at' => now(),
                'failed_at' => null,
                'refunded_at' => null,
            ])->save();

            $paid = (int) $booking->payments()
                ->where('status', BookingPaymentRecordStatus::Completed->value)
                ->lockForUpdate()
                ->sum('amount_minor');
            $booking->forceFill([
                'paid_minor' => $paid,
                'payment_status' => match (true) {
                    $paid <= 0 => BookingPaymentStatus::Unpaid,
                    $paid >= $booking->total_minor => BookingPaymentStatus::Paid,
                    default => BookingPaymentStatus::Partial,
                },
                'updated_by' => $actor->getKey(),
            ])->save();

            if ($tender->method === BookingPaymentMethod::Cash) {
                $shift->forceFill([
                    'expected_cash_minor' => $shift->expected_cash_minor + $tender->amountMinor,
                ])->save();
            }

            $this->activities->record(
                activityType: 'property-booking.payment.completed',
                description: "Payment recorded for booking {$booking->booking_number}",
                actor: $actor,
                subject: $payment,
                properties: [
                    'booking_ulid' => $booking->ulid,
                    'shift_ulid' => $shift->ulid,
                    'method' => $tender->method->value,
                    'amount_minor' => $tender->amountMinor,
                ],
                severity: SystemActivitySeverity::Notice,
                source: 'property-booking-pob',
            );
            BookingPaymentConfirmed::dispatch($booking->ulid, $payment->ulid, $booking->property_id);

            return $payment->refresh();
        });
    }

    /** Validate amount, configured method, shift ownership, currency, and change. */
    private function assertTender(Booking $booking, PobTenderData $tender, ReceptionShift $shift, User $actor): void
    {
        $methods = (array) config('property-booking.pob.payment_methods', []);
        if (! in_array($tender->method->value, $methods, true)) {
            throw new PointOfBookingException('The selected Point of Booking payment method is unavailable.');
        }
        if ($tender->amountMinor <= 0) {
            throw new PointOfBookingException('Payment amounts must be greater than zero.');
        }
        if (! $shift->isOpen() || $booking->reception_shift_id !== $shift->getKey()
            || $booking->property_id !== $shift->property_id) {
            throw new PointOfBookingException('Payments require the booking owning open reception shift.');
        }
        if ($booking->receptionist_id !== $actor->getKey()
            && ! $actor->can(PropertyBookingPermission::MANAGE_SHIFTS)) {
            throw new PointOfBookingException('Only the responsible receptionist or an authorized supervisor may record this payment.');
        }
        if ($booking->currency !== $shift->currency) {
            throw new PointOfBookingException('The booking and reception shift currencies do not match.');
        }
        if ($tender->method === BookingPaymentMethod::Cash
            && ($tender->tenderedMinor ?? $tender->amountMinor) < $tender->amountMinor) {
            throw new PointOfBookingException('Cash tendered cannot be less than the applied payment amount.');
        }
    }

    /** Normalize a bounded non-secret reference and require it for non-cash. */
    private function reference(PobTenderData $tender): ?string
    {
        $reference = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', trim((string) $tender->reference));
        $reference = is_string($reference) ? trim($reference) : '';
        if (strlen($reference) > 160) {
            throw new PointOfBookingException('Payment references cannot exceed 160 characters.');
        }
        if ($tender->method !== BookingPaymentMethod::Cash && $reference === '') {
            throw new PointOfBookingException('Non-cash payments require a transaction reference.');
        }

        return $reference !== '' ? $reference : null;
    }
}
